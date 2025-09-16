<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Livewire\Attributes\On;

class CustomerProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;

    #[On('open-modal-customer-project')]
    public function openModalCustomerProject($projectId)
    {
        $this->project = PlannerProject::with('customerProject')->findOrFail($projectId);
        $this->modalShow = true;
    }

    public function closeModal()
    {
        $this->modalShow = false;
    }

    public function render()
    {
        return view('planner::livewire.customer-project-settings-modal')->layout('platform::layouts.app');
    }
}


