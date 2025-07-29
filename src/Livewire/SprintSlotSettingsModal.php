<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Models\PlannerSprintSlot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class SprintSlotSettingsModal extends Component
{
    public $modalShow;
    public $sprintSlot;

    public function rules(): array
    {
        return [
            'sprintSlot.name' => 'required|string|max:255',
        ];
    }

    #[On('open-modal-sprint-slot-settings')] 
    public function openModalSprintSlotSettings($sprintSlotId)
    {
        $this->sprintSlot = PlannerSprintSlot::findOrFail($sprintSlotId);
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function save()
    {
        $this->sprintSlot->save();
        $this->reset('sprintSlot');
        $this->dispatch('sprintSlotUpdated');
        $this->closeModal();
    }

    public function deleteSprintSlot(): void
    {
        $this->sprintSlot->delete();
        $this->reset('sprintSlot');
        $this->dispatch('sprintSlotUpdated');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('planner::livewire.sprint-slot-settings-modal')->layout('platform::layouts.app');
    }
}