<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Models\PlannerTaskGroup;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class TaskGroupSettingsModal extends Component
{
    public $modalShow;
    public $taskGroup;

    public function rules(): array
    {
        return [
            'taskGroup.label' => 'required|string|max:255',
        ];
    }

    #[On('open-modal-task-group-settings')] 
    public function openModalTaskGroupSettings($taskGroupId)
    {
        $this->taskGroup = PlannerTaskGroup::findOrFail($taskGroupId);
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function save()
    {
        $this->taskGroup->save();
        $this->reset('taskGroup');
        $this->dispatch('taskGroupUpdated');
        $this->closeModal();
    }

    public function deleteTaskGroup(): void
    {
        $this->taskGroup->delete();
        $this->reset('taskGroup');
        $this->dispatch('taskGroupUpdated');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('planner::livewire.task-group-settings-modal')->layout('platform::layouts.app');
    }
}