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
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Models\PlannerTask;

/**
 * Kundenansicht für geteilte Knoten (Kunden-Tor): Projekte mit Fortschritt,
 * zuletzt erledigten und nächsten Schritten; Detailseite mit Board, Verlauf und
 * Canvases. Bewusst ohne Interna — keine Zeiten, Budgets, Verantwortlichen,
 * Story Points oder verworfenen Aufgaben; private Canvases bleiben intern.
 */
class PlannerShareView implements ProvidesShareView
{
    public function shareTypes(): array
    {
        return [
            'project' => ['label' => 'Projekte', 'singular' => 'Projekt', 'icon' => 'folder', 'featured' => true, 'slug' => 'projekt'],
            'planner_task' => ['label' => 'Aufgaben', 'singular' => 'Aufgabe', 'icon' => 'clipboard-document-check', 'featured' => false, 'slug' => 'aufgabe'],
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
                ['label' => 'Offen', 'value' => (string) $open->count()],
                ['label' => 'Erledigt', 'value' => (string) $done->count()],
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
            $canvas->load('blocks.entries');

            $detail['page'] = [
                'title' => $canvas->name,
                'view' => 'planner::share.canvas',
                'data' => [
                    'canvas' => $canvas,
                    'canvasData' => $canvas->toCanvasArray(),
                    'blockDefs' => collect(config('planner.canvas_block_types', []))
                        ->map(fn ($def, $key) => array_merge($def, ['key' => $key]))
                        ->sortBy('position')->values()->all(),
                ],
            ];

            return $detail;
        }

        if ($details) {
            $detail['tabs'][] = [
                'key' => 'board', 'label' => 'Board', 'count' => $open->count(),
                'view' => 'planner::share.board',
                'data' => ['columns' => $this->boardColumns($model, $open, $done)],
            ];
            $detail['tabs'][] = [
                'key' => 'verlauf', 'label' => 'Verlauf', 'count' => $done->count(),
                'view' => 'planner::share.history',
                'data' => ['weeks' => $this->historyWeeks($done)],
            ];
        }
        if ($canvases->isNotEmpty()) {
            $detail['tabs'][] = [
                'key' => 'canvases', 'label' => 'Canvases', 'count' => $canvases->count(),
                'view' => 'planner::share.canvases',
                'data' => ['canvases' => $canvases->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'description' => $c->description,
                    'status_label' => PlannerProjectCanvas::STATUS_LABELS[$c->status] ?? $c->status,
                    'done' => in_array($c->status, PlannerProjectCanvas::DONE_STATUSES, true),
                    'updated_at' => $c->updated_at,
                ])->all()],
            ];
        }

        return $detail;
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

        return [
            'id' => $task->id,
            'title' => $task->title,
            'done' => $isDone,
            'column' => $column,
            'description' => $task->description ?: null,
            'due' => $due?->copy()->locale('de')->isoFormat('D. MMM YYYY'),
            'due_phrase' => $due ? $this->duePhrase($due) : null,
            'overdue' => $due && ! $isDone && $due->isPast() && ! $due->isToday(),
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
