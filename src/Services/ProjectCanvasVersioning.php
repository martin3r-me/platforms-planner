<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Platform\Core\Models\User;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Models\PlannerProjectLogbookEntry;

/**
 * Versionierung des Projekt-Canvas — ohne dass jemand "versionieren" muss.
 *
 * Prinzip "Sitzungs-Checkpoint": Bevor ein Canvas nach einer Pause (SESSION_GAP_MINUTES)
 * wieder veraendert wird, wird der bisherige Stand gesichert. So bleibt der Stand am
 * Ende jeder Bearbeitungssitzung erhalten, ohne Scheduler und ohne Snapshot-Flut.
 * Identische Staende werden ueber einen Inhalts-Hash erkannt und nicht doppelt gesichert.
 *
 * v1 eines Projekts ist idealerweise der Kickoff-Stand; alles danach ist Abweichung davon.
 */
class ProjectCanvasVersioning
{
    public const SESSION_GAP_MINUTES = 30;

    public const SOURCE_AUTO = 'auto';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_KICKOFF = 'kickoff';
    public const SOURCE_AGENT = 'agent';
    public const SOURCE_RESTORE = 'restore';

    public const SOURCE_LABELS = [
        self::SOURCE_AUTO => 'Automatisch',
        self::SOURCE_MANUAL => 'Festgehalten',
        self::SOURCE_KICKOFF => 'Kickoff',
        self::SOURCE_AGENT => 'Agent',
        self::SOURCE_RESTORE => 'Wiederhergestellt',
    ];

    /** Canvas-IDs, fuer die in diesem Request schon ein Sitzungs-Checkpoint geprueft wurde. */
    protected static array $checkedThisRequest = [];

    /** Waehrend restore() keine Auto-Checkpoints aus den Entry-Hooks. */
    protected static bool $suspended = false;

    // ── Sichern ─────────────────────────────────────────────────────────────

    /**
     * Stand sichern. Ohne $force wird nichts angelegt, wenn der Inhalt dem letzten
     * Stand entspricht — dann kommt der letzte Stand zurueck.
     */
    public function checkpoint(PlannerProjectCanvas $canvas, string $source = self::SOURCE_MANUAL, ?int $userId = null, ?string $label = null, bool $force = false): PlannerProjectCanvasSnapshot
    {
        $canvas->unsetRelation('blocks');
        $data = $canvas->toCanvasArray();
        $hash = $this->contentHash($data);

        $latest = $canvas->snapshots()->orderByDesc('version')->first();
        if (! $force && $latest && $latest->content_hash === $hash) {
            return $latest;
        }

        return DB::transaction(function () use ($canvas, $data, $hash, $source, $userId, $label) {
            $version = ((int) $canvas->snapshots()->lockForUpdate()->max('version')) + 1;

            return $canvas->snapshots()->create([
                'version' => $version,
                'source' => $source,
                'label' => $label !== null ? mb_substr(trim($label), 0, 255) : null,
                'content_hash' => $hash,
                'snapshot_data' => $data,
                'created_by_user_id' => $userId,
            ]);
        });
    }

    /**
     * Sitzungs-Checkpoint vor einer inhaltlichen Aenderung (aus den Entry-Hooks).
     * Sichert den bisherigen Stand, wenn der letzte Stand aelter als die Sitzungspause
     * ist. Leere Canvases werden nicht gesichert.
     */
    public function beforeChange(PlannerProjectCanvas $canvas): void
    {
        if (self::$suspended || isset(self::$checkedThisRequest[$canvas->id])) {
            return;
        }
        self::$checkedThisRequest[$canvas->id] = true;

        $latestAt = $canvas->snapshots()->max('created_at');
        if ($latestAt && Carbon::parse($latestAt)->gt(now()->subMinutes(self::SESSION_GAP_MINUTES))) {
            return;
        }

        $hasEntries = PlannerProjectCanvasEntry::whereIn('block_id', $canvas->blocks()->select('id'))->exists();
        if (! $hasEntries) {
            return;
        }

        $this->checkpoint($canvas, self::SOURCE_AUTO, auth()->id());
    }

    /** Request-Cache leeren (Tests, Queue-Worker). */
    public static function flushRequestState(): void
    {
        self::$checkedThisRequest = [];
    }

    /** Hash nur ueber Inhalt (nicht Position/Metadaten) — Verschieben ist keine neue Version. */
    public function contentHash(array $canvasArray): string
    {
        $norm = [];
        foreach ($canvasArray['blocks'] ?? [] as $type => $block) {
            $entries = array_map(fn ($e) => [
                $e['uuid'] ?? null,
                trim((string) ($e['title'] ?? '')),
                trim((string) ($e['content'] ?? '')),
                $e['entry_type'] ?? null,
            ], $block['entries'] ?? []);
            usort($entries, fn ($a, $b) => strcmp((string) $a[0], (string) $b[0]));
            $norm[$type] = $entries;
        }
        ksort($norm);

        return hash('sha256', json_encode($norm));
    }

    // ── Vergleichen ─────────────────────────────────────────────────────────

    /**
     * Aenderungen zwischen zwei Canvas-Zustaenden (Arrays im Format toCanvasArray()).
     *
     * @return array{changes: array, has_changes: bool, summary: array{added: int, removed: int, modified: int}}
     */
    public function diff(array $before, array $after): array
    {
        $labels = [];
        foreach (config('planner.canvas_block_types', []) as $key => $cfg) {
            $labels[$key] = $cfg['label'] ?? $key;
        }

        $changes = [];
        $sum = ['added' => 0, 'removed' => 0, 'modified' => 0];
        $types = array_unique(array_merge(array_keys($before['blocks'] ?? []), array_keys($after['blocks'] ?? [])));

        foreach ($types as $type) {
            $a = collect($before['blocks'][$type]['entries'] ?? [])->keyBy('uuid');
            $b = collect($after['blocks'][$type]['entries'] ?? [])->keyBy('uuid');

            $added = $b->diffKeys($a)->map(fn ($e) => $this->entryView($e))->values()->all();
            $removed = $a->diffKeys($b)->map(fn ($e) => $this->entryView($e))->values()->all();
            $modified = [];
            foreach ($b->intersectByKeys($a) as $uuid => $eb) {
                $ea = $a[$uuid];
                if (trim((string) $ea['title']) !== trim((string) $eb['title']) || trim((string) $ea['content']) !== trim((string) $eb['content'])) {
                    $modified[] = ['uuid' => $uuid, 'before' => $this->entryView($ea), 'after' => $this->entryView($eb)];
                }
            }

            if ($added || $removed || $modified) {
                $changes[$type] = [
                    'label' => $labels[$type] ?? ($after['blocks'][$type]['label'] ?? $before['blocks'][$type]['label'] ?? $type),
                    'added' => $added,
                    'removed' => $removed,
                    'modified' => $modified,
                ];
                $sum['added'] += count($added);
                $sum['removed'] += count($removed);
                $sum['modified'] += count($modified);
            }
        }

        return ['changes' => $changes, 'has_changes' => ! empty($changes), 'summary' => $sum];
    }

    /** Aenderungen eines Stands gegenueber dem aktuellen Canvas. */
    public function diffToCurrent(PlannerProjectCanvasSnapshot $snapshot): array
    {
        $canvas = $snapshot->canvas;
        $canvas->unsetRelation('blocks');

        return $this->diff($snapshot->snapshot_data ?? [], $canvas->toCanvasArray());
    }

    /**
     * Was hat sich seit $since geaendert? Basis ist der letzte Stand bis $since
     * (sonst der aelteste Stand ueberhaupt); verglichen wird mit dem aktuellen Canvas.
     */
    public function changesSince(PlannerProjectCanvas $canvas, Carbon $since): array
    {
        $base = $canvas->snapshots()->where('created_at', '<=', $since)->orderByDesc('version')->first()
            ?? $canvas->snapshots()->orderBy('version')->first();

        $canvas->unsetRelation('blocks');
        $current = $canvas->toCanvasArray();

        return [
            'since' => $since->toISOString(),
            'base' => $base ? $this->snapshotMeta($base) : null,
            ...($base ? $this->diff($base->snapshot_data ?? [], $current) : $this->diff([], $current)),
        ];
    }

    // ── Wiederherstellen ────────────────────────────────────────────────────

    /**
     * Alten Stand wiederherstellen. Nichts geht verloren: der aktuelle Stand wird vorher
     * gesichert, der wiederhergestellte Stand danach als eigene Version festgehalten.
     */
    public function restore(PlannerProjectCanvasSnapshot $snapshot, ?int $userId = null): PlannerProjectCanvasSnapshot
    {
        $canvas = $snapshot->canvas;
        $this->checkpoint($canvas, self::SOURCE_AUTO, $userId, "Vor Wiederherstellung von v{$snapshot->version}");

        self::$suspended = true;
        try {
            DB::transaction(function () use ($canvas, $snapshot) {
                $blocks = $canvas->blocks()->get()->keyBy('block_type');
                $target = $snapshot->snapshot_data['blocks'] ?? [];

                $keepUuids = [];
                foreach ($target as $type => $blockData) {
                    $block = $blocks->get($type);
                    if (! $block) {
                        continue;
                    }
                    foreach ($blockData['entries'] ?? [] as $e) {
                        $uuid = $e['uuid'] ?? null;
                        if (! $uuid) {
                            continue;
                        }
                        $keepUuids[] = $uuid;
                        $entry = PlannerProjectCanvasEntry::withTrashed()->where('uuid', $uuid)->first();
                        $attrs = [
                            'block_id' => $block->id,
                            'title' => $e['title'] ?? null,
                            'content' => $e['content'] ?? null,
                            'entry_type' => $e['entry_type'] ?? null,
                            'position' => $e['position'] ?? 0,
                            'metadata' => $e['metadata'] ?? null,
                        ];
                        if ($entry) {
                            if ($entry->trashed()) {
                                $entry->restore();
                            }
                            $entry->fill($attrs)->save();
                        } else {
                            PlannerProjectCanvasEntry::create(['uuid' => $uuid, ...$attrs]);
                        }
                    }
                }

                PlannerProjectCanvasEntry::whereIn('block_id', $blocks->pluck('id'))
                    ->whereNotIn('uuid', $keepUuids)
                    ->get()
                    ->each->delete();
            });
        } finally {
            self::$suspended = false;
        }

        return $this->checkpoint($canvas->fresh(), self::SOURCE_RESTORE, $userId, "Wiederhergestellt aus v{$snapshot->version}", force: true);
    }

    // ── Festhalten + Kickoff (Verzahnung mit dem Logbuch) ───────────────────

    /**
     * "Stand festhalten": sichert den Stand und haengt optional einen Logbuch-Eintrag
     * mit Verweis auf genau diesen Stand an.
     *
     * @param array{type?: string, title?: string, rationale?: ?string}|null $logbook
     * @return array{snapshot: PlannerProjectCanvasSnapshot, entry: ?PlannerProjectLogbookEntry}
     */
    public function capture(PlannerProjectCanvas $canvas, User $user, ?string $label = null, ?array $logbook = null, string $source = self::SOURCE_MANUAL): array
    {
        // Identischer Inhalt wie der letzte automatische Stand → diesen benennen statt duplizieren.
        $snapshot = $this->checkpoint($canvas, $source, $user->id, $label);
        if ($snapshot->source !== $source || ($label !== null && $snapshot->label !== $label)) {
            if ($snapshot->source === self::SOURCE_AUTO) {
                $snapshot->forceFill(['source' => $source, 'label' => $label, 'created_by_user_id' => $user->id])->save();
            } else {
                $snapshot = $this->checkpoint($canvas, $source, $user->id, $label, force: true);
            }
        }

        $entry = null;
        if ($logbook && trim((string) ($logbook['title'] ?? '')) !== '') {
            $entry = app(ProjectLogbookService::class)->append($canvas->project, $user, [
                'type' => $logbook['type'] ?? LogbookEntryType::DECISION->value,
                'title' => $logbook['title'],
                'rationale' => $logbook['rationale'] ?? null,
                'refs' => [['type' => 'canvas_snapshot', 'ref' => (string) $snapshot->id]],
                'source' => $logbook['source'] ?? ($source === self::SOURCE_AGENT ? 'agent' : 'manual'),
            ]);
        }

        return ['snapshot' => $snapshot, 'entry' => $entry];
    }

    /**
     * Kickoff abschliessen: Kickoff-Stand sichern, Canvas markieren, Meilenstein ins Logbuch.
     *
     * @return array{snapshot: PlannerProjectCanvasSnapshot, entry: PlannerProjectLogbookEntry}
     */
    public function completeKickoff(PlannerProjectCanvas $canvas, User $user, ?string $note = null, string $source = self::SOURCE_KICKOFF): array
    {
        if ($canvas->kickoff_completed_at) {
            throw new InvalidArgumentException('Der Kickoff ist bereits abgeschlossen (' . $canvas->kickoff_completed_at->format('d.m.Y') . ').');
        }

        $goal = collect($canvas->toCanvasArray()['blocks']['project_goal']['entries'] ?? [])
            ->map(fn ($e) => trim((string) ($e['title'] ?: $e['content'])))
            ->filter()
            ->first();

        return DB::transaction(function () use ($canvas, $user, $note, $goal) {
            $result = $this->capture($canvas, $user, 'Kickoff', [
                'type' => LogbookEntryType::MILESTONE->value,
                'title' => 'Kickoff abgeschlossen',
                'rationale' => trim(implode("\n\n", array_filter([$goal ? "Ziel: {$goal}" : null, $note]))) ?: null,
            ], self::SOURCE_KICKOFF);

            $canvas->forceFill([
                'kickoff_completed_at' => now(),
                'kickoff_completed_by_user_id' => $user->id,
            ])->save();

            return $result;
        });
    }

    // ── Darstellung ─────────────────────────────────────────────────────────

    public function snapshotMeta(PlannerProjectCanvasSnapshot $s): array
    {
        return [
            'id' => $s->id,
            'version' => $s->version,
            'source' => $s->source,
            'source_label' => self::SOURCE_LABELS[$s->source] ?? $s->source,
            'label' => $s->label,
            'created_at' => $s->created_at?->toISOString(),
            'created_by' => $s->createdByUser?->name,
        ];
    }

    protected function entryView(array $e): array
    {
        return array_filter([
            'uuid' => $e['uuid'] ?? null,
            'title' => $e['title'] ?? null,
            'content' => $e['content'] ?? null,
            'entry_type' => $e['entry_type'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
