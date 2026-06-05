<?php

namespace Platform\Planner\Livewire\ProjectCanvas;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Services\ProjectCanvasStatusService;

class Index extends Component
{
    use WithPagination;

    public PlannerProject $project;
    public string $search = '';
    public string $statusFilter = '';

    public function mount(PlannerProject $plannerProject): void
    {
        $this->project = $plannerProject;
        $this->authorize('view', $this->project);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $this->statusFilter === $status ? '' : $status;
        $this->resetPage();
    }

    public function rendered(): void
    {
        $this->dispatch('comms', [
            'model' => get_class($this->project),
            'modelId' => $this->project->id,
            'subject' => $this->project->name . ' - Project Canvas',
            'description' => 'Canvas-Uebersicht',
            'url' => route('planner.projects.canvas.index', $this->project),
            'source' => 'planner.projects.canvas.index',
            'recipients' => [],
            'meta' => ['view_type' => 'index'],
        ]);
    }

    public function render()
    {
        $query = PlannerProjectCanvas::forProject($this->project->id)
            ->withCount('blocks', 'snapshots')
            ->with(['createdByUser', 'blocks.entries']);

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->statusFilter) {
            $query->byStatus($this->statusFilter);
        }

        $canvases = $query->orderBy('updated_at', 'desc')->paginate(15);

        $statusService = new ProjectCanvasStatusService();
        $canvasStatuses = [];
        foreach ($canvases as $canvas) {
            $canvasStatuses[$canvas->id] = $statusService->assessStatus($canvas);
        }

        $baseQuery = PlannerProjectCanvas::forProject($this->project->id);
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->byStatus('draft')->count(),
            'active' => (clone $baseQuery)->byStatus('active')->count(),
            'archived' => (clone $baseQuery)->byStatus('archived')->count(),
        ];

        return view('planner::livewire.project-canvas.index', [
            'canvases' => $canvases,
            'canvasStatuses' => $canvasStatuses,
            'stats' => $stats,
        ])->layout('platform::layouts.app');
    }
}
