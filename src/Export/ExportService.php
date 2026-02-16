<?php

namespace Platform\Planner\Export;

use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Export\Contracts\ExportFormatter;
use Platform\Planner\Export\Formatters\JsonExportFormatter;
use Platform\Planner\Export\Formatters\PdfExportFormatter;
use Illuminate\Support\Str;

/**
 * Zentraler Export-Service für den Planner.
 *
 * Koordiniert das Sammeln von Daten und delegiert die Formatierung
 * an registrierte Formatter. Erweiterbar für zukünftige Formate.
 */
class ExportService
{
    /**
     * Registrierte Formatter pro Format.
     *
     * @var array<string, ExportFormatter>
     */
    protected array $formatters = [];

    public function __construct()
    {
        $this->registerFormatter(ExportFormat::JSON, new JsonExportFormatter());
        $this->registerFormatter(ExportFormat::PDF, new PdfExportFormatter());
    }

    /**
     * Registriert einen Formatter für ein bestimmtes Format.
     */
    public function registerFormatter(ExportFormat $format, ExportFormatter $formatter): void
    {
        $this->formatters[$format->value] = $formatter;
    }

    /**
     * Gibt den Formatter für ein Format zurück.
     */
    public function getFormatter(ExportFormat $format): ExportFormatter
    {
        if (!isset($this->formatters[$format->value])) {
            throw new \InvalidArgumentException("Kein Formatter für Format '{$format->value}' registriert.");
        }

        return $this->formatters[$format->value];
    }

    /**
     * Gibt alle registrierten Formate zurück.
     *
     * @return ExportFormat[]
     */
    public function availableFormats(): array
    {
        return array_map(
            fn(string $key) => ExportFormat::from($key),
            array_keys($this->formatters)
        );
    }

    /**
     * Exportiert eine einzelne Aufgabe im gewünschten Format.
     */
    public function exportTask(PlannerTask $task, ExportFormat $format)
    {
        $data = $this->buildTaskData($task);
        $filename = $this->buildTaskFilename($task, $format);
        $formatter = $this->getFormatter($format);

        return $formatter->exportTask($data, $filename);
    }

    /**
     * Exportiert ein ganzes Projekt im gewünschten Format.
     */
    public function exportProject(PlannerProject $project, ExportFormat $format)
    {
        $data = $this->buildProjectData($project);
        $filename = $this->buildProjectFilename($project, $format);
        $formatter = $this->getFormatter($format);

        return $formatter->exportProject($data, $filename);
    }

    /**
     * Sammelt alle relevanten Daten einer Aufgabe für den Export.
     */
    public function buildTaskData(PlannerTask $task): array
    {
        $task->load(['user', 'userInCharge', 'project', 'projectSlot', 'taskGroup', 'team']);

        $data = [
            'id' => $task->id,
            'uuid' => $task->uuid,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority?->value,
            'priority_label' => $task->priority?->label(),
            'story_points' => $task->story_points?->value,
            'story_points_label' => $task->story_points?->label(),
            'story_points_numeric' => $task->story_points?->points(),
            'status' => $task->is_done ? 'erledigt' : 'offen',
            'is_done' => $task->is_done,
            'done_at' => $task->done_at?->toIso8601String(),
            'is_frog' => $task->is_frog,
            'due_date' => $task->due_date?->format('Y-m-d'),
            'original_due_date' => $task->original_due_date?->format('Y-m-d'),
            'postpone_count' => $task->postpone_count,
            'planned_minutes' => $task->planned_minutes,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
            'creator' => $task->user ? [
                'id' => $task->user->id,
                'name' => $task->user->name,
                'email' => $task->user->email,
            ] : null,
            'assignee' => $task->userInCharge ? [
                'id' => $task->userInCharge->id,
                'name' => $task->userInCharge->name,
                'email' => $task->userInCharge->email,
            ] : null,
            'team' => $task->team ? [
                'id' => $task->team->id,
                'name' => $task->team->name,
            ] : null,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
            ] : null,
            'project_slot' => $task->projectSlot ? [
                'id' => $task->projectSlot->id,
                'name' => $task->projectSlot->name,
            ] : null,
            'task_group' => $task->taskGroup ? [
                'id' => $task->taskGroup->id,
                'label' => $task->taskGroup->label,
            ] : null,
            'dod' => [
                'items' => $task->dod_items,
                'progress' => $task->dod_progress,
            ],
            'extra_fields' => $this->buildExtraFieldsData($task),
        ];

        return $data;
    }

    /**
     * Sammelt alle relevanten Daten eines Projekts für den Export.
     */
    public function buildProjectData(PlannerProject $project): array
    {
        $project->load([
            'user',
            'team',
            'projectSlots.tasks.user',
            'projectSlots.tasks.userInCharge',
            'projectUsers.user',
            'tasks.user',
            'tasks.userInCharge',
        ]);

        $data = [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
            'project_type' => $project->project_type?->value,
            'project_type_label' => $project->project_type?->label(),
            'done' => $project->done,
            'done_at' => $project->done_at?->toIso8601String(),
            'planned_minutes' => $project->planned_minutes,
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
            'creator' => $project->user ? [
                'id' => $project->user->id,
                'name' => $project->user->name,
                'email' => $project->user->email,
            ] : null,
            'team' => $project->team ? [
                'id' => $project->team->id,
                'name' => $project->team->name,
            ] : null,
            'members' => $project->projectUsers->map(fn($pu) => [
                'user_id' => $pu->user_id,
                'name' => $pu->user?->name,
                'email' => $pu->user?->email,
                'role' => $pu->role,
            ])->values()->toArray(),
            'extra_fields' => $this->buildExtraFieldsData($project),
            'statistics' => $this->buildProjectStatistics($project),
            'slots' => $this->buildSlotsData($project),
            'backlog_tasks' => $this->buildBacklogTasksData($project),
        ];

        return $data;
    }

    /**
     * Baut die Slot-Daten inkl. Tasks für den Projekt-Export.
     */
    protected function buildSlotsData(PlannerProject $project): array
    {
        return $project->projectSlots
            ->sortBy('order')
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'uuid' => $slot->uuid,
                    'name' => $slot->name,
                    'order' => $slot->order,
                    'tasks' => $slot->tasks
                        ->sortBy('project_slot_order')
                        ->map(fn($task) => $this->buildTaskData($task))
                        ->values()
                        ->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Baut die Backlog-Tasks (Tasks ohne Slot) für den Projekt-Export.
     */
    protected function buildBacklogTasksData(PlannerProject $project): array
    {
        return $project->tasks
            ->whereNull('project_slot_id')
            ->sortBy('order')
            ->map(fn($task) => $this->buildTaskData($task))
            ->values()
            ->toArray();
    }

    /**
     * Baut Projekt-Statistiken für den Export.
     */
    protected function buildProjectStatistics(PlannerProject $project): array
    {
        $allTasks = $project->tasks;

        $openTasks = $allTasks->where('is_done', false);
        $doneTasks = $allTasks->where('is_done', true);

        return [
            'total_tasks' => $allTasks->count(),
            'open_tasks' => $openTasks->count(),
            'completed_tasks' => $doneTasks->count(),
            'total_story_points' => $allTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
            'completed_story_points' => $doneTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
            'open_story_points' => $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
            'total_slots' => $project->projectSlots->count(),
            'total_members' => $project->projectUsers->count(),
        ];
    }

    /**
     * Baut die Extra-Fields-Daten für den Export.
     */
    protected function buildExtraFieldsData($model): array
    {
        if (!method_exists($model, 'extraFields')) {
            return [];
        }

        try {
            $extraFields = $model->extraFields;

            if (!$extraFields || $extraFields->isEmpty()) {
                return [];
            }

            return $extraFields->map(function ($field) {
                return [
                    'key' => $field->key ?? $field->name ?? null,
                    'label' => $field->label ?? $field->name ?? null,
                    'value' => $field->pivot->value ?? $field->value ?? null,
                    'type' => $field->type ?? null,
                ];
            })->values()->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Erzeugt einen Dateinamen für den Task-Export.
     */
    protected function buildTaskFilename(PlannerTask $task, ExportFormat $format): string
    {
        $slug = Str::slug($task->title, '-');
        $slug = Str::limit($slug, 50, '');

        return "aufgabe-{$task->id}-{$slug}.{$format->extension()}";
    }

    /**
     * Erzeugt einen Dateinamen für den Projekt-Export.
     */
    protected function buildProjectFilename(PlannerProject $project, ExportFormat $format): string
    {
        $slug = Str::slug($project->name, '-');
        $slug = Str::limit($slug, 50, '');

        return "projekt-{$project->id}-{$slug}.{$format->extension()}";
    }
}
