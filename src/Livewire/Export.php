<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Export\ExportService;
use Platform\Planner\Export\ExportFormat;

/**
 * Zentraler Export-Bereich im Planner.
 *
 * Ermöglicht den Export von einzelnen Aufgaben und ganzen Projekten
 * in verschiedenen Formaten (JSON, PDF). Erweiterbar für weitere Formate.
 */
class Export extends Component
{
    public ?int $selectedProjectId = null;
    public ?int $selectedTaskId = null;
    public string $exportType = 'project'; // 'project' oder 'task'
    public string $exportFormat = 'json';  // 'json' oder 'pdf'

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Planner Export',
            'description' => 'Export-Bereich für Aufgaben und Projekte',
            'url' => route('planner.export'),
            'source' => 'planner.export',
            'recipients' => [],
            'meta' => [
                'view_type' => 'export',
            ],
        ]);
    }

    /**
     * Baut die Export-URL für eine Aufgabe.
     */
    protected function buildTaskExportUrl(int $taskId, string $format): string
    {
        return url("/api/planner/export/tasks/{$taskId}?format={$format}");
    }

    /**
     * Baut die Export-URL für ein Projekt.
     */
    protected function buildProjectExportUrl(int $projectId, string $format): string
    {
        return url("/api/planner/export/projects/{$projectId}?format={$format}");
    }

    /**
     * Startet den Export und leitet zum Download-Endpoint weiter.
     */
    public function startExport()
    {
        if ($this->exportType === 'project' && $this->selectedProjectId) {
            return redirect()->to(
                $this->buildProjectExportUrl($this->selectedProjectId, $this->exportFormat)
            );
        }

        if ($this->exportType === 'task' && $this->selectedTaskId) {
            return redirect()->to(
                $this->buildTaskExportUrl($this->selectedTaskId, $this->exportFormat)
            );
        }
    }

    /**
     * Direkter Download einer einzelnen Aufgabe.
     */
    public function downloadTask(int $taskId, string $format = 'json')
    {
        return redirect()->to($this->buildTaskExportUrl($taskId, $format));
    }

    /**
     * Direkter Download eines Projekts.
     */
    public function downloadProject(int $projectId, string $format = 'json')
    {
        return redirect()->to($this->buildProjectExportUrl($projectId, $format));
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Projekte des Users (Team-basiert + Mitgliedschaft)
        $projectIds = PlannerProjectUser::where('user_id', $user->id)
            ->pluck('project_id')
            ->toArray();

        $projects = PlannerProject::where(function ($q) use ($team, $projectIds) {
                $q->where('team_id', $team->id)
                  ->orWhereIn('id', $projectIds);
            })
            ->orderBy('name')
            ->get();

        // Aufgaben: Eigene + zugewiesene + Projekt-Aufgaben
        $tasks = PlannerTask::where(function ($q) use ($user, $projectIds) {
                $q->where('user_id', $user->id)
                  ->orWhere('user_in_charge_id', $user->id)
                  ->orWhereIn('project_id', $projectIds);
            })
            ->where('is_done', false)
            ->orderBy('title')
            ->limit(100)
            ->get();

        // Verfügbare Formate
        $exportService = new ExportService();
        $formats = array_map(fn(ExportFormat $f) => [
            'key' => $f->value,
            'label' => $f->label(),
        ], $exportService->availableFormats());

        return view('planner::livewire.export', [
            'projects' => $projects,
            'tasks' => $tasks,
            'formats' => $formats,
        ])->layout('platform::layouts.app');
    }
}
