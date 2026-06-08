<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectCanvas;

class PublicCanvas extends Component
{
    public PlannerProject $project;
    public PlannerProjectCanvas $canvas;
    public string $token;

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

    public function render()
    {
        $this->canvas->load(['blocks.entries', 'createdByUser']);

        $canvasData = $this->canvas->toCanvasArray();
        $blockDefs = collect(config('planner.canvas_block_types', []))
            ->map(fn ($def, $key) => array_merge($def, ['key' => $key]))
            ->values()
            ->toArray();

        $siblingCanvases = PlannerProjectCanvas::where('project_id', $this->project->id)
            ->where('is_public', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('planner::livewire.public-canvas', [
            'canvasData' => $canvasData,
            'blockDefs' => $blockDefs,
            'siblingCanvases' => $siblingCanvases,
        ])->layout('platform::layouts.guest');
    }
}
