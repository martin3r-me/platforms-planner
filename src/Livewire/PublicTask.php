<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerTask;

class PublicTask extends Component
{
    public PlannerProject $project;
    public PlannerTask $task;
    public string $token;

    public function mount(string $token, PlannerTask $task): void
    {
        $this->token = $token;

        $this->project = PlannerProject::where('public_token', $token)
            ->where('is_public', true)
            ->where(function ($q) {
                $q->whereNull('public_token_expires_at')
                  ->orWhere('public_token_expires_at', '>', now());
            })
            ->firstOrFail();

        // Ensure task belongs to this project
        abort_unless($task->project_id === $this->project->id, 404);

        $this->task = $task->loadMissing(['tags', 'contextColors', 'userInCharge', 'project', 'team']);
    }

    public function render()
    {
        return view('planner::livewire.public-task', [
            'dodItems' => $this->task->dod_items,
            'dodProgress' => $this->task->has_dod ? $this->task->dod_progress : null,
        ])->layout('platform::layouts.guest');
    }
}
