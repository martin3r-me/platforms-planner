<?php

namespace Platform\Planner\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Platform\Organization\Contracts\ProvidesShareView;
use Platform\Planner\Enums\ProjectLifecycleState;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasEntry;
use Platform\Planner\Models\PlannerProjectLogbookEntry;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Kundenansicht für geteilte Knoten (Kunden-Tor): Projekte mit Fortschritt,
 * zuletzt erledigten und nächsten Schritten, Archiv und Aktivitätsstrom;
 * Detailseite mit Canvases (Zustand), Board und Verlauf. Zeiten ergänzt organization.
 * Ohne Budgets, Story Points oder verworfene Aufgaben; private Canvases bleiben intern.
 */
class PlannerShareView implements ProvidesShareView
{
    public function shareTypes(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => true, 'slug' => 'projekt', 'group' => 'arbeit', 'order' => 10],
            // Einzelne Aufgaben am Knoten: nur als Liste, ohne eigene Detailseite
            'planner_task' => ['label' => 'Aufgaben', 'singular' => 'Aufgabe', 'icon' => 'clipboard-document-check', 'featured' => false, 'slug' => 'aufgabe', 'group' => 'arbeit', 'detail' => false, 'order' => 20],
        ];
    }

    public function shareItems(string $alias, Collection $models, array $options): array
    {
        return match ($alias) {
            'project' => $this->projects($models, (bool) ($options['details'] ?? true)),
            'planner_task' => $this->tasks($models),
            default => [],
        };
    }

    protected function projects(Collection $projects, bool $details): array
    {
        $projects = $projects->filter(fn ($p) => $p->lifecycle_state !== ProjectLifecycleState::DISCARDED);
        if ($projects->isEmpty()) {
            return [];
        }

        $tasksByProject = PlannerTask::whereIn('project_id', $projects->pluck('id'))
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value)
            ->get(['id', 'project_id', 'title', 'lifecycle_state', 'lifecycle_state_changed_at', 'due_date', 'order', 'updated_at'])
            ->groupBy('project_id');

        return $projects->map(function ($project) use ($tasksByProject, $details) {
            $tasks = $tasksByProject->get($project->id, collect());
            [$done, $open] = $tasks->partition(fn ($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED);

            $state = match ($project->lifecycle_state) {
                ProjectLifecycleState::COMPLETED => ['done', 'Abgeschlossen'],
                ProjectLifecycleState::DORMANT => ['paused', 'Pausiert'],
                default => ['active', 'Läuft'],
            };

            $item = [
                'id' => $project->id,
                'title' => $project->name,
                'subtitle' => null,
                'state' => $state[0],
                'state_label' => $state[1],
                'progress' => ['done' => $done->count(), 'total' => $tasks->count()],
                'due' => null,
                'updated_at' => collect([$project->updated_at, $tasks->max('updated_at')])->filter()->max(),
                'counts' => ['open' => $open->count(), 'done' => $done->count()],
                'started_at' => $project->created_at,
                'state_since' => $project->lifecycle_state_changed_at,
                'reason' => $state[0] === 'paused' ? ($project->lifecycle_state_reason ?: null) : null,
            ];

            if ($details) {
                $item['recent'] = $done->sortByDesc(fn ($t) => $t->lifecycle_state_changed_at?->timestamp ?? 0)
                    ->take(5)
                    ->map(fn ($t) => ['title' => $t->title, 'at' => $t->lifecycle_state_changed_at])
                    ->values()->all();
                $item['next'] = $open->sortBy(fn ($t) => [$t->due_date ? 0 : 1, $t->due_date?->timestamp ?? 0, $t->order ?? 0])
                    ->take(5)
                    ->map(fn ($t) => ['title' => $t->title, 'due' => $t->due_date])
                    ->values()->all();
            }

            return $item;
        })->values()->all();
    }

    protected function tasks(Collection $tasks): array
    {
        return $tasks->filter(fn ($t) => $t->lifecycle_state !== TaskLifecycleState::DISCARDED)
            ->map(function ($t) {
                $done = $t->lifecycle_state === TaskLifecycleState::COMPLETED;

                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'state' => $done ? 'done' : 'active',
                    'state_label' => $done ? 'Erledigt' : ($t->due_date ? 'bis ' . $t->due_date->copy()->locale('de')->isoFormat('D. MMM') : 'Offen'),
                    'due' => $t->due_date,
                    'updated_at' => $t->updated_at,
                ];
            })->values()->all();
    }

    // ── Aktivität ──────────────────────────────────────────────

    /** Ereignisse der letzten 60 Tage: erledigte Schritte, neue Schritte, Canvas-Arbeit, Logbuch, Statuswechsel. */
    public function shareActivity(string $alias, Collection $models, array $options): array
    {
        $from = now()->subDays(60);
        $events = [];

        if ($alias === 'planner_task') {
            foreach ($models as $t) {
                if ($t->lifecycle_state === TaskLifecycleState::COMPLETED && $t->lifecycle_state_changed_at?->gte($from)) {
                    $events[] = ['at' => $t->lifecycle_state_changed_at, 'kind' => 'done', 'title' => $t->title, 'text' => null, 'parent_id' => $t->id];
                }
            }

            return $events;
        }
        if ($alias !== 'project') {
            return [];
        }

        $projects = $models->filter(fn ($p) => $p->lifecycle_state !== ProjectLifecycleState::DISCARDED)->keyBy('id');
        if ($projects->isEmpty()) {
            return [];
        }

        foreach ($projects as $p) {
            if ($p->lifecycle_state_changed_at?->gte($from) && $p->lifecycle_state !== ProjectLifecycleState::ACTIVE) {
                $events[] = [
                    'at' => $p->lifecycle_state_changed_at, 'kind' => 'state',
                    'title' => $p->lifecycle_state === ProjectLifecycleState::COMPLETED ? 'Projekt abgeschlossen' : 'Projekt pausiert',
                    'text' => $p->lifecycle_state === ProjectLifecycleState::DORMANT ? ($p->lifecycle_state_reason ?: null) : null,
                    'parent_id' => $p->id,
                ];
            }
        }

        $tasks = PlannerTask::whereIn('project_id', $projects->keys())
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value)
            ->where(fn ($q) => $q->where('lifecycle_state_changed_at', '>=', $from)->orWhere('created_at', '>=', $from))
            ->get(['id', 'project_id', 'title', 'lifecycle_state', 'lifecycle_state_changed_at', 'created_at']);
        foreach ($tasks as $t) {
            if ($t->lifecycle_state === TaskLifecycleState::COMPLETED && $t->lifecycle_state_changed_at?->gte($from)) {
                $events[] = ['at' => $t->lifecycle_state_changed_at, 'kind' => 'done', 'title' => $t->title, 'text' => null, 'parent_id' => $t->project_id];
            }
        }
        // Neu geplante Schritte je Projekt und Tag zusammengefasst
        foreach ($tasks->filter(fn ($t) => $t->created_at?->gte($from))->groupBy(fn ($t) => $t->project_id . '|' . $t->created_at->format('Y-m-d')) as $group) {
            $events[] = [
                'at' => $group->max('created_at'), 'kind' => 'planned',
                'title' => $group->count() === 1 ? 'Neuer Schritt: ' . $group->first()->title : $group->count() . ' neue Schritte geplant',
                'text' => $group->count() > 1 ? $group->pluck('title')->take(4)->implode(' · ') . ($group->count() > 4 ? ' …' : '') : null,
                'parent_id' => $group->first()->project_id,
            ];
        }

        // Canvas-Arbeit je Canvas und Tag
        $canvases = PlannerProjectCanvas::whereIn('project_id', $projects->keys())
            ->where('visibility', PlannerProjectCanvas::VISIBILITY_TEAM)
            ->where('status', '!=', PlannerProjectCanvas::STATUS_DISCARDED)
            ->get(['id', 'project_id', 'name'])->keyBy('id');
        if ($canvases->isNotEmpty()) {
            $entries = PlannerProjectCanvasEntry::withTrashed()
                ->join('planner_project_canvas_blocks as b', 'b.id', '=', 'planner_project_canvas_entries.block_id')
                ->whereIn('b.canvas_id', $canvases->keys())
                ->where(fn ($q) => $q->where('planner_project_canvas_entries.updated_at', '>=', $from)->orWhere('planner_project_canvas_entries.deleted_at', '>=', $from))
                ->get(['planner_project_canvas_entries.id', 'planner_project_canvas_entries.title', 'planner_project_canvas_entries.created_at', 'planner_project_canvas_entries.updated_at', 'planner_project_canvas_entries.deleted_at', 'b.canvas_id']);
            foreach ($entries->groupBy(fn ($e) => $e->canvas_id . '|' . ($e->deleted_at ?? $e->updated_at)->format('Y-m-d')) as $group) {
                $canvas = $canvases[$group->first()->canvas_id];
                $new = $group->filter(fn ($e) => ! $e->deleted_at && $e->created_at?->gte($from) && $e->created_at->diffInMinutes($e->updated_at) < 5)->count();
                $removed = $group->filter(fn ($e) => $e->deleted_at)->count();
                $changed = $group->count() - $new - $removed;
                $events[] = [
                    'at' => $group->map(fn ($e) => $e->deleted_at ?? $e->updated_at)->max(), 'kind' => 'canvas',
                    'title' => 'Canvas „' . $canvas->name . '“ bearbeitet',
                    'text' => collect([$new ? $new . ' neu' : null, $changed ? $changed . ' geändert' : null, $removed ? $removed . ' entfernt' : null])->filter()->implode(' · ')
                        . ($group->whereNull('deleted_at')->pluck('title')->filter()->isNotEmpty() ? ' — ' . $group->whereNull('deleted_at')->pluck('title')->filter()->take(3)->map(fn ($t) => strip_tags((string) $t))->implode(', ') : ''),
                    'parent_id' => $canvas->project_id,
                    'sub' => 'canvas-' . $canvas->id,
                ];
            }
        }

        // Logbuch: Entscheidungen, Meilensteine, Risiken (ersetzte nicht)
        foreach (PlannerProjectLogbookEntry::whereIn('project_id', $projects->keys())->current()->where('created_at', '>=', $from)->get() as $e) {
            $events[] = [
                'at' => $e->created_at, 'kind' => 'logbook',
                'title' => $e->type->label() . ': ' . $e->title,
                'text' => $e->rationale ? \Illuminate\Support\Str::limit((string) $e->rationale, 200) : null,
                'parent_id' => $e->project_id,
            ];
        }

        return $events;
    }

    // ── Detailseite ────────────────────────────────────────────

    public function shareDetail(string $alias, Model $model, array $options): ?array
    {
        if ($alias !== 'project' || ! $model instanceof PlannerProject || $model->lifecycle_state === ProjectLifecycleState::DISCARDED) {
            return null;
        }

        $details = (bool) ($options['details'] ?? true);
        $sub = $options['sub'] ?? null;
        $canvases = PlannerProjectCanvas::where('project_id', $model->id)
            ->where('visibility', PlannerProjectCanvas::VISIBILITY_TEAM)
            ->where('status', '!=', PlannerProjectCanvas::STATUS_DISCARDED)
            ->orderByDesc('updated_at')
            ->get();

        $tasks = PlannerTask::where('project_id', $model->id)
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value)
            ->with(['userInCharge', 'teamTags'])
            ->orderBy('project_slot_order')
            ->get();
        [$done, $open] = $tasks->partition(fn ($t) => $t->lifecycle_state === TaskLifecycleState::COMPLETED);

        $state = match ($model->lifecycle_state) {
            ProjectLifecycleState::COMPLETED => ['done', 'Abgeschlossen'],
            ProjectLifecycleState::DORMANT => ['paused', 'Pausiert'],
            default => ['active', 'Läuft'],
        };
        $lastMove = collect([$model->updated_at, $tasks->max('updated_at')])->filter()->max();

        $detail = [
            'title' => $model->name,
            'subtitle' => null,
            'description' => $model->description ?: null,
            'state' => $state[0],
            'state_label' => $state[1],
            'progress' => ['done' => $done->count(), 'total' => $tasks->count()],
            'facts' => array_values(array_filter([
                ['label' => 'Offene Schritte', 'value' => (string) $open->count()],
                $lastMove ? ['label' => 'Zuletzt bewegt', 'value' => $lastMove->diffInMinutes(now()) < 1 ? 'gerade eben' : $lastMove->copy()->locale('de')->diffForHumans()] : null,
            ])),
            'tabs' => [],
        ];

        // Unterseite: ein Canvas
        if ($sub !== null) {
            $canvas = preg_match('/^canvas-(\d+)$/', $sub, $m) ? $canvases->firstWhere('id', (int) $m[1]) : null;
            if (! $canvas) {
                return null;
            }
            $detail['page'] = ['title' => $canvas->name] + $this->canvasPage($canvas, $options);

            return $detail;
        }

        if ($details) {
            $detail['tabs'][] = [
                'key' => 'board', 'label' => 'Board', 'count' => $open->count(), 'order' => 30,
                'view' => 'planner::share.board',
                'data' => ['columns' => $this->boardColumns($model, $open, $done)],
            ];
            $detail['tabs'][] = [
                'key' => 'erledigt', 'label' => 'Erledigt', 'count' => $done->count(), 'order' => 40,
                'view' => 'planner::share.history',
                'data' => ['weeks' => $this->historyWeeks($done)],
            ];
        }
        // Ein Canvas pro Projekt: der Reiter zeigt es direkt; Karten nur noch für Altbestand mit mehreren
        if ($canvases->count() === 1) {
            $page = $this->canvasPage($canvases->first(), $options);
            $detail['tabs'][] = [
                'key' => 'canvas', 'label' => 'Canvas', 'count' => null, 'order' => 10,
                'view' => 'planner::share.canvas-tab',
                'data' => $page['data'],
            ];
        } elseif ($canvases->isNotEmpty()) {
            $detail['tabs'][] = [
                'key' => 'canvases', 'label' => 'Canvases', 'count' => $canvases->count(), 'order' => 10,
                'view' => 'planner::share.canvases',
                'data' => ['canvases' => $this->canvasCards($canvases, $options['since'] ?? null)],
            ];
        }
        if ($details && ($logbook = $this->logbook($model))) {
            $detail['tabs'][] = [
                'key' => 'logbuch', 'label' => 'Logbuch', 'count' => count($logbook), 'order' => 35,
                'view' => 'planner::share.logbook',
                'data' => ['entries' => $logbook],
            ];
        }

        return $detail;
    }

    /** Ein Canvas zum Lesen: Blöcke wie intern, oben was sich seit dem letzten Besuch (sonst 7 Tagen) geändert hat. */
    protected function canvasPage(PlannerProjectCanvas $canvas, array $options): array
    {
        $canvas->load('blocks.entries');
        $blockDefs = collect(config('planner.canvas_block_types', []));
        $sinceAt = $options['since'] ?? now()->subDays(7);
        $changes = $canvas->blocks->flatMap(fn ($b) => $b->entries->filter(fn ($e) => $e->updated_at?->gt($sinceAt))
            ->map(fn ($e) => [
                'block' => $b->label ?: ($blockDefs[$b->block_type]['label'] ?? $b->block_type),
                'title' => strip_tags((string) ($e->title ?: \Illuminate\Support\Str::limit((string) $e->content, 80))),
                'new' => $e->created_at?->gt($sinceAt),
                'at' => $e->updated_at,
            ]))->sortByDesc(fn ($c) => $c['at']->timestamp)->values()->all();

        return [
            'view' => 'planner::share.canvas',
            'data' => [
                'canvas' => $canvas,
                'canvasData' => $canvas->toCanvasArray(),
                'changes' => $changes,
                'sinceIsVisit' => ($options['since'] ?? null) !== null,
                'updatedAt' => $canvas->blocks->flatMap->entries->max('updated_at') ?? $canvas->updated_at,
                'blockDefs' => $blockDefs->map(fn ($def, $key) => array_merge($def, ['key' => $key]))->sortBy('position')->values()->all(),
            ],
        ];
    }

    /** Logbuch ohne ersetzte Einträge, neueste zuerst — Wendepunkte mit Begründung; Verweise (Commits, IDs) bleiben intern. */
    protected function logbook(PlannerProject $project): array
    {
        return PlannerProjectLogbookEntry::forProject($project->id)->current()
            ->with('createdBy')
            ->orderByDesc('occurred_on')->orderByDesc('id')
            ->get()
            ->map(fn ($e) => [
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'icon' => $e->type->icon(),
                'title' => $e->title,
                'rationale' => $e->rationale ?: null,
                'date' => $e->occurred_on ?? $e->created_at,
                'person' => $e->createdBy ? ($e->createdBy->fullname ?: $e->createdBy->name) : null,
            ])->all();
    }

    /** Canvas-Karten: Status, wie viel ausgefüllt ist, was sich seit dem letzten Besuch getan hat. */
    protected function canvasCards(Collection $canvases, $since): array
    {
        $since ??= now()->subDays(7);
        $defs = collect(config('planner.canvas_block_types', []));
        $canvases->load('blocks.entries');

        return $canvases->map(function ($c) use ($since, $defs) {
            $entries = $c->blocks->flatMap->entries;
            $filled = $c->blocks->filter(fn ($b) => $b->entries->isNotEmpty());
            $last = $entries->max('updated_at') ?? $c->updated_at;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'status_label' => PlannerProjectCanvas::STATUS_LABELS[$c->status] ?? $c->status,
                'done' => in_array($c->status, PlannerProjectCanvas::DONE_STATUSES, true),
                'updated_at' => $last,
                'entries' => $entries->count(),
                'blocks_filled' => $filled->count(),
                'blocks_total' => max($c->blocks->count(), $defs->count() ?: $c->blocks->count()),
                'changed' => $entries->filter(fn ($e) => $e->updated_at?->gt($since))->count(),
                'preview' => $filled->sortBy('position')->take(3)->map(fn ($b) => [
                    'label' => $b->label ?: ($defs[$b->block_type]['label'] ?? $b->block_type),
                    'items' => $b->entries->sortBy('position')->take(3)->map(fn ($e) => strip_tags((string) ($e->title ?: \Illuminate\Support\Str::limit((string) $e->content, 60))))->filter()->values()->all(),
                ])->values()->all(),
            ];
        })->sortByDesc(fn ($c) => optional($c['updated_at'])->timestamp ?? 0)->values()->all();
    }

    /** Spalten wie im Board: Backlog · Slots (Ton wie intern) · Erledigt. */
    protected function boardColumns(PlannerProject $project, Collection $open, Collection $done): array
    {
        $palette = ['indigo', 'amber', 'teal', 'violet', 'sky', 'pink', 'rose', 'emerald'];
        $valid = array_merge($palette, ['slate']);
        $slots = PlannerProjectSlot::where('project_id', $project->id)->orderBy('order')->get();
        $bySlot = $open->groupBy(fn ($t) => $t->project_slot_id ?? 0);

        $columns = [];
        $backlog = $bySlot->get(0, collect());
        if ($backlog->isNotEmpty()) {
            $columns[] = ['key' => 'backlog', 'title' => 'Backlog', 'tone' => 'slate', 'muted' => true, 'tasks' => $backlog->map(fn ($t) => $this->card($t, 'Backlog'))->values()->all()];
        }
        foreach ($slots->values() as $i => $slot) {
            $columns[] = [
                'key' => 'slot-' . $slot->id,
                'title' => $slot->name,
                'tone' => in_array($slot->color, $valid, true) ? $slot->color : $palette[$i % count($palette)],
                'muted' => false,
                'tasks' => $bySlot->get($slot->id, collect())->map(fn ($t) => $this->card($t, $slot->name))->values()->all(),
            ];
        }
        $columns[] = [
            'key' => 'done', 'title' => 'Erledigt', 'tone' => 'emerald', 'muted' => true,
            'tasks' => $done->sortByDesc(fn ($t) => $t->lifecycle_state_changed_at?->timestamp ?? 0)
                ->take(40)->map(fn ($t) => $this->card($t, 'Erledigt'))->values()->all(),
            'total' => $done->count(),
        ];

        return $columns;
    }

    /** Kundentaugliche Karte einer Aufgabe (auch für das Detail-Blatt). */
    protected function card(PlannerTask $task, string $column): array
    {
        $isDone = $task->lifecycle_state === TaskLifecycleState::COMPLETED;
        $due = $task->due_date;
        $dod = $task->has_dod ? $task->dod_progress : null;
        $overdue = $due && ! $isDone && $due->isPast() && ! $due->isToday();
        // Farbband links wie im Planner: überfällig › erledigt › Frosch › Priorität › Farbe
        $edge = match (true) {
            $overdue => 'var(--nx-danger)',
            $isDone, (bool) $task->is_frog => 'var(--nx-success)',
            (bool) $task->priority => $task->priority->color(),
            (bool) $task->color => $task->color,
            default => 'var(--nx-line-strong)',
        };
        // Nur Team-Tags — persönliche Tags bleiben privat
        $tags = $task->relationLoaded('teamTags') ? $task->teamTags : collect();
        $person = $task->userInCharge;
        $personName = $person ? (trim((string) ($person->fullname ?? '')) ?: $person->name) : null;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'done' => $isDone,
            'column' => $column,
            'description' => $task->description ?: null,
            'due' => $due?->copy()->locale('de')->isoFormat('D. MMM YYYY'),
            'due_phrase' => $due ? $this->duePhrase($due) : null,
            'overdue' => $overdue,
            'edge' => $edge,
            'priority' => $task->priority?->label(),
            'tag' => ($t = $tags->first()) ? ['label' => $t->label, 'color' => $t->color ?: '#94a3b8'] : null,
            'tags_more' => max(0, $tags->count() - 1),
            'tags' => $tags->pluck('label')->values()->all(),
            'person' => $personName,
            'person_initials' => $personName ? mb_strtoupper(mb_substr($personName, 0, 1)) : null,
            'done_at' => $isDone ? $task->lifecycle_state_changed_at?->copy()->locale('de')->isoFormat('D. MMM YYYY') : null,
            'dod' => $dod ? ['checked' => $dod['checked'], 'total' => $dod['total'], 'pct' => (int) $dod['percentage']] : null,
            'dod_items' => $task->dod_items,
        ];
    }

    protected function duePhrase(Carbon $due): string
    {
        $diff = (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);

        return match (true) {
            $diff < 0 => abs($diff) . ' T. überfällig',
            $diff === 0 => 'heute',
            $diff === 1 => 'morgen',
            $diff < 7 => 'in ' . $diff . ' T.',
            default => $due->format('d.m.'),
        };
    }

    /** Erledigtes nach Kalenderwochen, neueste zuerst. */
    protected function historyWeeks(Collection $done): array
    {
        return $done->filter(fn ($t) => $t->lifecycle_state_changed_at)
            ->sortByDesc(fn ($t) => $t->lifecycle_state_changed_at->timestamp)
            ->take(120)
            ->groupBy(fn ($t) => $t->lifecycle_state_changed_at->copy()->startOfWeek()->format('Y-m-d'))
            ->map(function ($tasks, $week) {
                $start = Carbon::parse($week)->locale('de');

                return [
                    'label' => 'KW ' . $start->isoWeek() . ' · ' . $start->isoFormat('D. MMM') . ' – ' . $start->copy()->endOfWeek()->isoFormat('D. MMM YYYY'),
                    'tasks' => $tasks->map(fn ($t) => ['title' => $t->title, 'at' => $t->lifecycle_state_changed_at->copy()->locale('de')->isoFormat('dd, D. MMM')])->values()->all(),
                ];
            })->values()->all();
    }
}
