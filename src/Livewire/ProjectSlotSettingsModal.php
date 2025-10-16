<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Models\PlannerProjectSlot;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; 

class ProjectSlotSettingsModal extends Component
{
    public $modalShow;
    public $projectSlot;

    public function rules(): array
    {
        return [
            'projectSlot.name' => 'required|string|max:255',
        ];
    }

    #[On('open-modal-project-slot-settings')] 
    public function openModalProjectSlotSettings($payload)
    {
        // Payload kann als ID oder als Array/Objekt { projectSlotId: X } kommen
        $id = is_array($payload)
            ? ($payload['projectSlotId'] ?? $payload['id'] ?? null)
            : (is_object($payload) ? ($payload->projectSlotId ?? $payload->id ?? null) : $payload);

        $this->projectSlot = PlannerProjectSlot::findOrFail($id);
        $this->modalShow = true;
    }

    public function mount()
    {
        $this->modalShow = false;
    }

    public function save()
    {
        $this->projectSlot->save();
        $this->dispatch('projectSlotUpdated');
        $this->closeModal();
    }

    public function deleteProjectSlot(): void
    {
        $this->projectSlot->delete();
        $this->dispatch('projectSlotUpdated');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('planner::livewire.project-slot-settings-modal')->layout('platform::layouts.app');
    }
}
