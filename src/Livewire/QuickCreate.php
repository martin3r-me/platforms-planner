<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectCreationService;

/**
 * Zentrales "+ Neu" der Planner-Actionbar. Unsichtbar; die Actionbar schickt
 * $dispatch('planner-create', { type: 'task'|'project', projectId }).
 */
class QuickCreate extends Component
{
    #[On('planner-create')]
    public function create(string $type, ?int $projectId = null)
    {
        $user = Auth::user();
        $service = app(ProjectCreationService::class);

        if ($type === 'project') {
            [$project, $canvas] = $service->createDefault($user);

            return $this->redirectRoute('planner.projects.canvas.show', [$project->id, $canvas->id], navigate: true);
        }

        if ($type === 'task') {
            $project = $projectId ? PlannerProject::findOrFail($projectId) : null;
            if ($project) {
                $this->authorize('update', $project);
            }
            $task = $service->createTask($user, $project);

            return $this->redirectRoute('planner.tasks.show', $task, navigate: true);
        }
    }

    public function render()
    {
        return <<<'HTML'
            <div hidden></div>
        HTML;
    }
}
