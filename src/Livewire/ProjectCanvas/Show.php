<?php

namespace Platform\Planner\Livewire\ProjectCanvas;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasStatusService;

class Show extends Component
{
    public PlannerProject $project;
    public PlannerProjectCanvas $canvas;

    public function mount(PlannerProject $plannerProject, PlannerProjectCanvas $canvas): void
    {
        $this->project = $plannerProject;

        abort_unless($canvas->project_id === $plannerProject->id, 404);
        abort_unless($canvas->team_id === Auth::user()->currentTeam->id, 403);

        $this->canvas = $canvas;
    }

    public function rendered(): void
    {
        $this->dispatch('comms', [
            'model' => get_class($this->canvas),
            'modelId' => $this->canvas->id,
            'subject' => $this->canvas->name,
            'description' => 'Project Canvas',
            'url' => route('planner.projects.canvas.show', [$this->project, $this->canvas]),
            'source' => 'planner.projects.canvas.show',
            'recipients' => [],
            'meta' => ['view_type' => 'show'],
        ]);
    }

    public function render()
    {
        $this->canvas->load(['blocks.entries', 'createdByUser', 'snapshots']);

        $canvasData = $this->canvas->toCanvasArray();
        $statusData = (new ProjectCanvasStatusService())->assessStatus($this->canvas);

        $blockTypes = config('planner.canvas_block_types', []);

        return view('planner::livewire.project-canvas.show', [
            'canvasData' => $canvasData,
            'statusData' => $statusData,
            'blockTypes' => $blockTypes,
        ])->layout('platform::layouts.app');
    }
}
