<?php

namespace Platform\Planner\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectCanvasService;
use Platform\Planner\Support\ProjectDashboardData;

/** Projekt-Reiter "Ueberblick": Fortschritt, Zeit/Budget, Canvas-Briefing, Verlauf, Risiken, Beteiligte. */
class ProjectOverview extends Component
{
    public PlannerProject $project;

    public function mount(PlannerProject $plannerProject): void
    {
        $this->authorize('view', $plannerProject);
        $this->project = $plannerProject;
    }

    public function openCanvas()
    {
        $this->authorize('view', $this->project);
        $canvas = app(ProjectCanvasService::class)->ensureForProject($this->project, auth()->id());

        return $this->redirectRoute('planner.projects.canvas.show', [$this->project, $canvas], navigate: true);
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        return view('planner::livewire.project-overview', ProjectDashboardData::build($this->project));
    }
}
