<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;
use Platform\Planner\Models\PlannerProjectCanvasWorkshopNote;
use Platform\Planner\Services\ProjectCanvasAnalysisService;

class PublicCanvas extends Component
{
    public PlannerProject $project;
    public PlannerProjectCanvas $canvas;
    public string $token;
    public string $viewMode = 'list';

    public function mount(string $token, PlannerProjectCanvas $canvas): void
    {
        $this->token = $token;

        $this->project = PlannerProject::where('public_token', $token)
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('public_token_expires_at')
                  ->orWhere('public_token_expires_at', '>', now());
            })
            ->firstOrFail();

        abort_unless($canvas->project_id === $this->project->id, 404);
        abort_unless($canvas->is_public, 404);

        $this->canvas = $canvas;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode === 'workshop' ? 'workshop' : 'list';
    }

    public function render()
    {
        $this->canvas->load(['blocks.entries', 'createdByUser']);

        $canvasData = $this->canvas->toCanvasArray();
        $analysisData = (new ProjectCanvasAnalysisService())->analyze($this->canvas);
        $layout = config('planner.canvas_layout', [
            'type' => 'grid',
            'columns' => 3,
            'rows' => 3,
            'areas' => '',
            'area_map' => [],
        ]);
        $blockDefs = collect(config('planner.canvas_block_types', []))
            ->map(fn ($def, $key) => array_merge($def, ['key' => $key]))
            ->values()
            ->toArray();

        $siblingCanvases = PlannerProjectCanvas::where('project_id', $this->project->id)
            ->where('is_public', true)
            ->orderBy('created_at', 'desc')
            ->get();

        $workshopNotes = [];
        if ($this->viewMode === 'workshop') {
            $workshopNotes = $this->canvas->workshopNotes()
                ->orderBy('created_at')
                ->get()
                ->map(fn (PlannerProjectCanvasWorkshopNote $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'content' => $n->content ?? '',
                    'color' => $n->color,
                    'type' => $n->type ?? 'note',
                    'x' => $n->position_x,
                    'y' => $n->position_y,
                    'width' => $n->width,
                    'height' => $n->height,
                    'metadata' => $n->metadata,
                ])
                ->values()
                ->toArray();
        }

        return view('planner::livewire.public-canvas', [
            'canvasData' => $canvasData,
            'analysisData' => $analysisData,
            'layout' => $layout,
            'blockDefs' => $blockDefs,
            'workshopNotes' => $workshopNotes,
            'siblingCanvases' => $siblingCanvases,
        ])->layout('platform::layouts.guest');
    }
}
