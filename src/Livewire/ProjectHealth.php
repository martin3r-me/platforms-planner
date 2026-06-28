<?php

namespace Platform\Planner\Livewire;

use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Services\ProjectSnapshotService;

class ProjectHealth extends Component
{
    public PlannerProject $project;

    public int $trendDays = 30;

    public function mount(PlannerProject $plannerProject): void
    {
        $this->authorize('view', $plannerProject);
        $this->project = $plannerProject;
    }

    public function setTrendDays(int $days): void
    {
        $this->trendDays = max(7, min(180, $days));
    }

    /**
     * Erstellt einen neuen Snapshot on-demand (manueller Trigger ueber UI-Button).
     */
    public function refreshSnapshot(ProjectSnapshotService $service): void
    {
        $this->authorize('view', $this->project);
        $service->snapshot($this->project, 'manual');

        $this->dispatch('notifications:store', [
            'title' => 'Snapshot aktualisiert',
            'message' => 'Der Health-Stand wurde gerade neu berechnet.',
            'notice_type' => 'success',
            'noticable_type' => PlannerProject::class,
            'noticable_id' => $this->project->id,
        ]);
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $latest = PlannerProjectSnapshot::with(['slots', 'frogs', 'people'])
            ->where('project_id', $this->project->id)
            ->orderByDesc('taken_on')
            ->first();

        $from = Carbon::now()->subDays($this->trendDays - 1)->toDateString();
        $to = Carbon::now()->toDateString();

        $trend = PlannerProjectSnapshot::where('project_id', $this->project->id)
            ->whereBetween('taken_on', [$from, $to])
            ->orderBy('taken_on')
            ->get([
                'id',
                'taken_on',
                'health_score',
                'health_color',
                'worst_axis',
                'axis_scores',
                'canvas_score',
                'confidence_score',
                'tasks_open',
                'tasks_done',
                'tasks_overdue',
                'tasks_frog',
                'minutes_logged',
            ]);

        return view('planner::livewire.project-health', [
            'project' => $this->project,
            'latest' => $latest,
            'trend' => $trend,
            'trendFrom' => $from,
            'trendTo' => $to,
        ]);
    }
}
