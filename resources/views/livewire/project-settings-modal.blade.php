<x-ui-modal size="md" model="modalShow" header="Project Settings">

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
                    <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                        <span class="text-[var(--ui-muted)]">Projektname</span>
                        <span class="font-medium text-[var(--ui-body-color)]">{{ $project->name }}</span>
                    </div>
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
                    <div class="flex items-start justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                        <span class="text-[var(--ui-muted)] mr-3">Projekt Beschreibung</span>
                        <span class="font-medium text-[var(--ui-body-color)] text-right">{{ $project->description }}</span>
                    </div>
                @endcan
            </x-ui-form-grid>

            <x-ui-form-grid :cols="1" :gap="6">
                {{-- Projekttyp Toggle (lokal) --}}
                @php $ptype = ($project->project_type?->value ?? $project->project_type); @endphp
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-[var(--ui-body-color)]">Projekttyp</label>
                    <div class="inline-flex rounded-lg border border-[var(--ui-border)] bg-white overflow-hidden">
                        <button type="button"
                                wire:click="setProjectType('internal')"
                                class="inline-flex items-center gap-2 text-sm h-8 px-3 {{ $ptype==='internal' ? 'bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)]' : 'bg-white text-[var(--ui-body-color)] hover:bg-[var(--ui-muted-5)]' }} border-r border-[var(--ui-border)]">
                            Intern
                        </button>
                        <button type="button"
                                wire:click="setProjectType('customer')"
                                @if($ptype==='customer') disabled @endif
                                class="inline-flex items-center gap-2 text-sm h-8 px-3 {{ $ptype==='customer' ? 'bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)]' : 'bg-white text-[var(--ui-body-color)] hover:bg-[var(--ui-muted-5)]' }}">
                            Kunde
                        </button>
                    </div>
                    <div class="text-xs text-[var(--ui-muted)]">
                        {{ $ptype==='customer' ? 'Typ: Kunde (nicht zurücksetzbar)' : 'Typ: Intern' }}
                    </div>
                </div>

                {{-- Kundenprojekt: vorerst keine Eingaben, nur Hinweis --}}
                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <x-ui-info-banner
                        message="Kundenprojekt wurde angelegt. Weitere Einstellungen folgen."
                        variant="info"
                    />
                @endif

                {{-- Beteiligte Benutzer --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium">Projekt-Teilnehmer</h3>
                    
                    {{-- Aktuelle Teilnehmer --}}
                    <div class="space-y-2">
                        @foreach($project->projectUsers as $projectUser)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-medium">
                                        {{ substr($projectUser->user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $projectUser->user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $projectUser->user->email }}</div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    {{-- Rolle ändern --}}
                                    @can('changeRole', $project)
                                        <select 
                                            wire:change="changeUserRole({{ $projectUser->user_id }}, $event.target.value)"
                                            class="text-sm border rounded px-2 py-1"
                                            @if($projectUser->role === \Platform\Planner\Enums\ProjectRole::OWNER->value) disabled @endif
                                        >
                                            @foreach(\Platform\Planner\Enums\ProjectRole::cases() as $role)
                                                <option value="{{ $role->value }}" 
                                                    @if($projectUser->role === $role->value) selected @endif
                                                >
                                                    {{ ucfirst($role->value) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-sm font-medium px-2 py-1 bg-gray-200 rounded">
                                            {{ ucfirst($projectUser->role) }}
                                        </span>
                                    @endcan
                                    
                                    {{-- Teilnehmer entfernen --}}
                                    @can('removeMember', $project)
                                        @if($projectUser->role !== \Platform\Planner\Enums\ProjectRole::OWNER->value)
                                            <button 
                                                wire:click="removeProjectUser({{ $projectUser->user_id }})"
                                                class="text-red-500 hover:text-red-700 text-sm"
                                            >
                                                Entfernen
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    {{-- Neuen Teilnehmer hinzufügen --}}
                    @can('invite', $project)
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium mb-3">Teilnehmer hinzufügen</h4>
                            
                            @php
                                $availableUsers = $this->getAvailableUsers();
                            @endphp
                            
                            @if($availableUsers->count() > 0)
                                <div class="space-y-2">
                                    @foreach($availableUsers as $user)
                                        <div class="flex items-center justify-between p-2 border rounded">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-6 h-6 bg-gray-300 text-gray-700 rounded-full flex items-center justify-center text-xs font-medium">
                                                    {{ substr($user->name, 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="font-medium text-sm">{{ $user->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                            <button 
                                                wire:click="addProjectUser({{ $user->id }}, 'member')"
                                                class="text-primary hover:text-primary-dark text-sm font-medium"
                                            >
                                                Hinzufügen
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Alle Team-Mitglieder sind bereits im Projekt.</p>
                            @endif
                        </div>
                    @endcan
                    
                    {{-- Ownership übertragen --}}
                    @can('transferOwnership', $project)
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium mb-3 text-orange-600">Ownership übertragen</h4>
                            <p class="text-sm text-gray-600 mb-3">Vorsicht: Dies überträgt die Projektleitung an einen anderen User.</p>
                            
                            <select 
                                wire:change="transferOwnership($event.target.value)"
                                class="w-full border rounded px-3 py-2"
                            >
                                <option value="">Ownership übertragen an...</option>
                                @foreach($project->projectUsers as $projectUser)
                                    @if($projectUser->role !== \Platform\Planner\Enums\ProjectRole::OWNER->value)
                                        <option value="{{ $projectUser->user_id }}">
                                            {{ $projectUser->user->name }} ({{ ucfirst($projectUser->role) }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @endcan
                </div>
                
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
</x-ui-modal>