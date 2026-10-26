<?php

namespace Platform\Planner\Services;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Platform\Core\Models\User;
use Platform\Core\Verbalization\Contracts\ReportEngine;
use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Planner\Enums\LogbookEntryType;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasSnapshot;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Verbalization\PlannerProjectSubjectCollector;

/**
 * Projekt-Logbuch: Anhaengen mit Verweis-Pruefung + Entwurf aus der Faktenlage.
 *
 * Abgrenzung der drei Achsen: Canvas = Zustand (ueberschreiben), Logbuch = Verlauf
 * (anhaengen), Aufgaben/Zeiten = Arbeit. Das Logbuch wiederholt Arbeit nicht, es
 * verweist darauf (refs) und haelt das "warum" fest.
 */
class ProjectLogbookService
{
    /**
     * @param array{type: string, title: string, rationale?: ?string, occurred_on?: ?string, refs?: ?array, supersedes_entry_id?: ?int, source?: ?string} $data
     *
     * @throws InvalidArgumentException bei ungueltigen Eingaben (Meldung ist nutzertauglich)
     */
    public function append(PlannerProject $project, User $user, array $data): PlannerProjectLogbookEntry
    {
        $type = LogbookEntryType::tryFrom((string) ($data['type'] ?? ''));
        if (! $type) {
            throw new InvalidArgumentException('type muss einer von ' . implode(', ', LogbookEntryType::values()) . ' sein.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('title ist erforderlich.');
        }
        if (mb_strlen($title) > 255) {
            throw new InvalidArgumentException('title darf hoechstens 255 Zeichen haben.');
        }

        $rationale = trim((string) ($data['rationale'] ?? ''));

        $occurredOn = ! empty($data['occurred_on'])
            ? Carbon::parse($data['occurred_on'])->startOfDay()
            : now()->startOfDay();
        if ($occurredOn->isAfter(now()->endOfDay())) {
            throw new InvalidArgumentException('occurred_on darf nicht in der Zukunft liegen — das Logbuch haelt fest, was passiert ist.');
        }

        $supersedesId = ! empty($data['supersedes_entry_id']) ? (int) $data['supersedes_entry_id'] : null;
        if ($supersedesId) {
            $old = PlannerProjectLogbookEntry::where('project_id', $project->id)->find($supersedesId);
            if (! $old) {
                throw new InvalidArgumentException("Eintrag {$supersedesId} gehoert nicht zu diesem Projekt.");
            }
            if ($old->supersededBy()->exists()) {
                throw new InvalidArgumentException("Eintrag {$supersedesId} wurde bereits ersetzt — bitte den neuesten Eintrag ersetzen.");
            }
        }

        $source = in_array($data['source'] ?? null, PlannerProjectLogbookEntry::SOURCES, true)
            ? $data['source']
            : 'manual';

        return PlannerProjectLogbookEntry::create([
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'type' => $type,
            'title' => $title,
            'rationale' => $rationale !== '' ? $rationale : null,
            'occurred_on' => $occurredOn->toDateString(),
            'refs' => $this->normalizeRefs($project, $data['refs'] ?? []),
            'supersedes_entry_id' => $supersedesId,
            'source' => $source,
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * Verweise pruefen und vereinheitlichen. Projekt-interne Verweise (Task, Canvas,
     * Canvas-Snapshot) muessen zum Projekt gehoeren; Labels werden fuer die Anzeige
     * eingefroren, damit der Eintrag auch nach Umbenennungen lesbar bleibt.
     *
     * @return array<int, array{type: string, ref: string, label: ?string}>
     */
    public function normalizeRefs(PlannerProject $project, mixed $refs): array
    {
        if (! is_array($refs)) {
            throw new InvalidArgumentException('refs muss ein Array von {type, ref, label?} sein.');
        }

        $out = [];
        foreach (array_values($refs) as $i => $ref) {
            if (! is_array($ref)) {
                throw new InvalidArgumentException("refs[{$i}] muss ein Objekt {type, ref, label?} sein.");
            }
            $type = (string) ($ref['type'] ?? '');
            $value = trim((string) ($ref['ref'] ?? ''));
            $label = isset($ref['label']) && trim((string) $ref['label']) !== '' ? trim((string) $ref['label']) : null;

            if ($value === '') {
                throw new InvalidArgumentException("refs[{$i}].ref fehlt.");
            }

            switch ($type) {
                case 'task':
                    $task = PlannerTask::withTrashed()->withStale()->where('project_id', $project->id)->find((int) $value);
                    if (! $task) {
                        throw new InvalidArgumentException("refs[{$i}]: Aufgabe {$value} gehoert nicht zu diesem Projekt.");
                    }
                    $value = (string) $task->id;
                    $label ??= $task->title;
                    break;

                case 'canvas':
                    $canvas = PlannerProjectCanvas::withTrashed()->where('project_id', $project->id)->find((int) $value);
                    if (! $canvas) {
                        throw new InvalidArgumentException("refs[{$i}]: Canvas {$value} gehoert nicht zu diesem Projekt.");
                    }
                    $value = (string) $canvas->id;
                    $label ??= $canvas->name;
                    break;

                case 'canvas_snapshot':
                    $snapshot = PlannerProjectCanvasSnapshot::whereIn(
                        'canvas_id',
                        PlannerProjectCanvas::withTrashed()->where('project_id', $project->id)->select('id')
                    )->find((int) $value);
                    if (! $snapshot) {
                        throw new InvalidArgumentException("refs[{$i}]: Canvas-Snapshot {$value} gehoert nicht zu diesem Projekt.");
                    }
                    $value = (string) $snapshot->id;
                    $label ??= "Canvas-Stand v{$snapshot->version}";
                    break;

                case 'commit':
                    // "sha" oder "repo@sha" — der Planner kennt keine Repos, nur die Referenz.
                    if (! preg_match('/^(?:[\w.\-\/]+@)?[0-9a-f]{7,40}$/i', $value)) {
                        throw new InvalidArgumentException("refs[{$i}]: Commit-Referenz muss \"sha\" oder \"repo@sha\" sein (7-40 Hex-Zeichen).");
                    }
                    break;

                case 'url':
                    if (! preg_match('#^https?://#i', $value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new InvalidArgumentException("refs[{$i}]: ungueltige URL.");
                    }
                    break;

                default:
                    throw new InvalidArgumentException("refs[{$i}].type muss einer von " . implode(', ', PlannerProjectLogbookEntry::REF_TYPES) . ' sein.');
            }

            $out[] = ['type' => $type, 'ref' => $value, 'label' => $label !== null ? mb_substr($label, 0, 200) : null];
        }

        return $out;
    }

    /**
     * Entwurfs-Motor: Faktenprosa seit dem letzten Eintrag (planner.project.verbalize-
     * Pipeline) plus Verweis-Vorschlaege. Schreibt NICHTS — der Mensch kuratiert daraus
     * einen Eintrag.
     *
     * @return array{since: string, prose: ?string, fact_sheet: ?string, suggested_refs: array, llm: bool, error: ?string}
     */
    public function draft(PlannerProject $project, ?Carbon $since = null, bool $useLlm = true): array
    {
        $since ??= $this->defaultDraftSince($project);

        $subject = app(PlannerProjectSubjectCollector::class)->collectState($project, null, $since);

        $prose = null;
        $factSheet = null;
        $error = null;

        if ($useLlm) {
            try {
                $result = app(ReportEngine::class)->verbalize(
                    subject: $subject,
                    style: StyleProfile::collegial(),
                    rails: new GuardRails(),
                );
                $prose = $result->prose;
                $factSheet = $result->factSheet;
            } catch (\Throwable $e) {
                // Kein LLM verfuegbar → deterministische Faktenbasis reicht als Entwurf.
                $error = $e->getMessage();
            }
        }

        if ($factSheet === null) {
            $template = app(\Platform\Core\Verbalization\Template\TemplateRegistry::class)->resolve($subject->type);
            $factSheet = $template?->renderFactSheet($subject);
        }

        return [
            'since' => $since->toDateString(),
            'prose' => $prose,
            'fact_sheet' => $factSheet,
            'suggested_refs' => $this->suggestRefs($project, $since),
            'llm' => $prose !== null,
            'error' => $error,
        ];
    }

    /** Seit dem letzten Eintrag, sonst seit Projektanlage, hoechstens 90 Tage zurueck. */
    public function defaultDraftSince(PlannerProject $project): Carbon
    {
        $last = PlannerProjectLogbookEntry::where('project_id', $project->id)->max('created_at');
        $since = $last ? Carbon::parse($last) : Carbon::parse($project->created_at ?? now()->subDays(30));

        return $since->max(now()->subDays(90))->startOfDay();
    }

    /** Verweis-Kandidaten seit $since: erledigte Aufgaben + neue Canvas-Staende. */
    public function suggestRefs(PlannerProject $project, Carbon $since): array
    {
        $refs = PlannerTask::withStale()->where('project_id', $project->id)
            ->where('lifecycle_state', TaskLifecycleState::COMPLETED->value)
            ->where('lifecycle_state_changed_at', '>=', $since)
            ->orderByDesc('lifecycle_state_changed_at')
            ->limit(15)
            ->get(['id', 'title'])
            ->map(fn ($t) => ['type' => 'task', 'ref' => (string) $t->id, 'label' => $t->title])
            ->all();

        $snapshots = PlannerProjectCanvasSnapshot::with('canvas:id,name')
            ->whereIn('canvas_id', PlannerProjectCanvas::where('project_id', $project->id)->select('id'))
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        foreach ($snapshots as $s) {
            $refs[] = ['type' => 'canvas_snapshot', 'ref' => (string) $s->id, 'label' => ($s->canvas?->name ?? 'Canvas') . " v{$s->version}"];
        }

        return $refs;
    }
}
