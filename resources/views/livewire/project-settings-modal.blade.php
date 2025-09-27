<x-ui-organisms-modal size="md" model="modalShow" header="Project Settings">

    @if($project)
            <x-ui-form-grid :cols="1" :gap="4">
                {{-- Projekt Name --}}
                @can('update', $project)
                    <x-ui-input-text 
                        name="project.name"
                        label="Projektname"
                        wire:model.live.debounce.500ms="project.name"
                        placeholder="Projekt Name eingeben..."
                        required
                        :errorKey="'project.name'"
                    />
                @else
                    <x-ui-info-display
                        label="Projektname"
                        :value="$project->name"
                    />
                @endcan

                {{-- Beschreibung --}}
                @can('update', $project)
                    <x-ui-input-textarea 
                        name="project.description"
                        label="Projekt Beschreibung"
                        wire:model.live.debounce.500ms="project.description"
                        placeholder="Projekt Beschreibung eingeben..."
                        :errorKey="'project.description'"
                    />
                @else
                    <x-ui-info-display
                        label="Projekt Beschreibung"
                        :value="$project->description"
                    />
                @endcan
            </x-ui-form-grid>

            <x-ui-form-grid :cols="1" :gap="6">
                {{-- Projekttyp Toggle --}}
                <x-ui-toggle-buttons
                    name="projectType"
                    label="Projekttyp"
                    :options="[
                        ['value' => 'internal', 'label' => 'Intern'],
                        ['value' => 'customer', 'label' => 'Kunde']
                    ]"
                    :value="($project->project_type?->value ?? $project->project_type)"
                    :disabled="($project->project_type?->value ?? $project->project_type) === 'customer'"
                    :infoText="($project->project_type?->value ?? $project->project_type) === 'customer' ? 'Typ: Kunde (nicht zurücksetzbar)' : 'Typ: Intern'"
                />

                {{-- Kundenprojekt: vorerst keine Eingaben, nur Hinweis --}}
                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <x-ui-info-banner
                        message="Kundenprojekt wurde angelegt. Weitere Einstellungen folgen."
                        variant="info"
                    />
                @endif

                {{-- Beteiligte Benutzer --}}
                <x-ui-project-members-list
                    :users="$teamUsers"
                    :project="$project"
                    :canUpdate="auth()->user()->can('update', $project)"
                    :roles="\Platform\Planner\Enums\ProjectRole::cases()"
                    :ownerRoleValue="\Platform\Planner\Enums\ProjectRole::OWNER->value"
                />
                
                {{-- Projekt löschen --}}
                @can('delete', $project)
                        <x-ui-confirm-button action="deleteProject" text="Projekt löschen" confirmText="Wirklich löschen?" />
                @endcan
            </x-ui-form-grid>
    @endif

    <x-slot name="footer">
        @can('update', $project)
            <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
        @endcan
    </x-slot>
</x-ui-organisms-modal>