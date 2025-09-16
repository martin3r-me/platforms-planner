<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Livewire\Attributes\On;
use Platform\Planner\Models\PlannerCustomerProject;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmCompanyOptionsProviderInterface;

class CustomerProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;
    public $companyId = null;
    public $companyDisplay = null;
    public $companyOptions = [];

    #[On('open-modal-customer-project')]
    public function openModalCustomerProject($projectId)
    {
        $this->project = PlannerProject::with('customerProject')->findOrFail($projectId);
        $this->companyId = $this->project->customerProject?->company_id;
        $this->resolveCompanyDisplay();
        $this->loadCompanyOptions();
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

    public function updatedCompanyId($value)
    {
        $this->resolveCompanyDisplay();
    }

    private function resolveCompanyDisplay(): void
    {
        /** @var CrmCompanyResolverInterface $resolver */
        $resolver = app(CrmCompanyResolverInterface::class);
        $this->companyDisplay = $resolver->displayName($this->companyId ? (int)$this->companyId : null);
    }

    private function loadCompanyOptions(?string $q = null): void
    {
        /** @var CrmCompanyOptionsProviderInterface $provider */
        $provider = app(CrmCompanyOptionsProviderInterface::class);
        $this->companyOptions = $provider->options($q, 50);
    }

    public function saveCompany()
    {
        if (! $this->project) {
            return;
        }

        PlannerCustomerProject::updateOrCreate(
            ['project_id' => $this->project->id],
            [
                'project_id' => $this->project->id,
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
                'company_id' => $this->companyId ? (int)$this->companyId : null,
            ]
        );

        $this->project->refresh();
        $this->resolveCompanyDisplay();
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Kundenfirma gespeichert',
        ]);
    }
}


