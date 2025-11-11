<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Livewire\Attributes\On;
use Platform\Planner\Models\PlannerCustomerProject;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\CrmCompanyResolverInterface;
use Platform\Core\Contracts\CrmCompanyOptionsProviderInterface;
use Platform\Core\Contracts\CrmCompanyContactsProviderInterface;
use Platform\Crm\Models\CrmContactLink;
use Platform\Crm\Models\CrmContact;

class CustomerProjectSettingsModal extends Component
{
    public $modalShow = false;
    public $project;
    public $companyId = null;
    public $companyDisplay = null;
    public $companyOptions = [];
    public $companySearch = '';
    public $companyData = null;
    public $companyContacts = [];
    public $projectContacts = [];
    public $selectedContactIds = [];

    #[On('open-modal-customer-project')]
    public function openModalCustomerProject($projectId)
    {
        $this->project = PlannerProject::with('customerProject')->findOrFail($projectId);
        $this->companyId = $this->project->customerProject?->company_id;
        $this->resolveCompanyDisplay();
        $this->loadCompanyOptions('');
        $this->loadCompanyData();
        $this->loadCompanyContacts();
        $this->loadProjectContacts();
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
        $this->loadCompanyData();
        $this->loadCompanyContacts();
        $this->loadProjectContacts();
    }

    public function updatedCompanySearch($value)
    {
        $this->loadCompanyOptions($this->companySearch);
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
        $options = $provider->options($q, 50);

        // Konvertiere zu Collection für die Komponente
        $this->companyOptions = collect($options);

        // Falls aktuelle Auswahl nicht in den Optionen ist, füge sie als erste Option hinzu
        if ($this->companyId) {
            $companyId = (int)$this->companyId;
            $exists = $this->companyOptions->firstWhere('value', $companyId);
            
            if (!$exists) {
                /** @var CrmCompanyResolverInterface $resolver */
                $resolver = app(CrmCompanyResolverInterface::class);
                $label = $resolver->displayName($companyId);
                
                // Nur hinzufügen, wenn ein Label gefunden wurde
                if ($label) {
                    $this->companyOptions->prepend([
                        'value' => $companyId,
                        'label' => $label,
                    ]);
                }
            }
        }
    }

    private function loadCompanyData(): void
    {
        if (!$this->companyId) {
            $this->companyData = null;
            return;
        }

        /** @var CrmCompanyResolverInterface $resolver */
        $resolver = app(CrmCompanyResolverInterface::class);
        
        $this->companyData = [
            'name' => $resolver->displayName((int)$this->companyId),
            'url' => $resolver->url((int)$this->companyId),
        ];
    }

    private function loadCompanyContacts(): void
    {
        if (!$this->companyId) {
            $this->companyContacts = [];
            return;
        }

        /** @var CrmCompanyContactsProviderInterface $provider */
        $provider = app(CrmCompanyContactsProviderInterface::class);
        $this->companyContacts = $provider->contacts((int)$this->companyId);
    }

    private function loadProjectContacts(): void
    {
        if (!$this->project) {
            $this->projectContacts = [];
            return;
        }

        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;
        $teamId = $baseTeam ? $baseTeam->getRootTeam()->id : null;

        if (!$teamId) {
            $this->projectContacts = [];
            return;
        }

        $links = CrmContactLink::where('linkable_type', PlannerProject::class)
            ->where('linkable_id', $this->project->id)
            ->where('team_id', $teamId)
            ->with(['contact.emailAddresses', 'contact.contactStatus'])
            ->get();

        $this->projectContacts = $links->map(function ($link) {
            $contact = $link->contact;
            if (!$contact) {
                return null;
            }

            $emailAddress = $contact->emailAddresses()
                ->where('is_primary', true)
                ->first()
                ?? $contact->emailAddresses()->first();

            return [
                'id' => $contact->id,
                'link_id' => $link->id,
                'name' => $contact->display_name,
                'email' => $emailAddress?->email_address,
            ];
        })
        ->filter()
        ->values()
        ->all();

        // Selected IDs für Checkboxen (initial aus bereits verknüpften Kontakten)
        $this->selectedContactIds = collect($this->projectContacts)->pluck('id')->toArray();
    }

    public function toggleContact($contactId): void
    {
        if (!in_array($contactId, $this->selectedContactIds)) {
            $this->selectedContactIds[] = $contactId;
        } else {
            $this->selectedContactIds = array_values(array_diff($this->selectedContactIds, [$contactId]));
        }
    }

    public function saveContacts(): void
    {
        if (!$this->project) {
            return;
        }

        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;
        $teamId = $baseTeam ? $baseTeam->getRootTeam()->id : null;

        if (!$teamId) {
            return;
        }

        // Aktuelle Links holen
        $currentLinks = CrmContactLink::where('linkable_type', PlannerProject::class)
            ->where('linkable_id', $this->project->id)
            ->where('team_id', $teamId)
            ->get();

        $currentContactIds = $currentLinks->pluck('contact_id')->toArray();
        $selectedContactIds = array_map('intval', $this->selectedContactIds);

        // Zu entfernende Links
        $toRemove = array_diff($currentContactIds, $selectedContactIds);
        foreach ($toRemove as $contactId) {
            $link = $currentLinks->firstWhere('contact_id', $contactId);
            if ($link && $link->created_by_user_id === Auth::id()) {
                $link->delete();
            }
        }

        // Neue Links hinzufügen
        $toAdd = array_diff($selectedContactIds, $currentContactIds);
        foreach ($toAdd as $contactId) {
            // Prüfe ob Kontakt existiert und zum Root-Team gehört
            $contact = CrmContact::find($contactId);
            if ($contact && $contact->team_id === $teamId && $contact->is_active) {
                CrmContactLink::create([
                    'contact_id' => $contactId,
                    'linkable_type' => PlannerProject::class,
                    'linkable_id' => $this->project->id,
                    'team_id' => $teamId,
                    'created_by_user_id' => Auth::id(),
                ]);
            }
        }

        $this->loadProjectContacts();
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
        
        // Kontakte speichern (auch wenn leer, um Entfernungen zu speichern)
        $this->saveContacts();
        
        $this->dispatch('updateProject');
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Kundenfirma gespeichert',
        ]);
        $this->closeModal();
    }
}


