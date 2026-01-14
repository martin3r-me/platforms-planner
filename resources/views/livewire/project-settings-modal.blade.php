<div x-data="{ activeTab: 'general' }">
    <x-ui-modal size="md" model="modalShow" header="Project Settings">
        @if($project)
            {{-- Tabs --}}
            <div class="flex gap-1 mb-6 border-b border-[var(--ui-border)]">
                <button
                    type="button"
                    @click="activeTab = 'general'"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                    :class="activeTab === 'general' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                >
                    Allgemein
                </button>
                <button
                    type="button"
                    @click="activeTab = 'recurring'"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                    :class="activeTab === 'recurring' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                >
                    Wiederkehrende Aufgaben
                </button>
            </div>

            {{-- Tab: Allgemein --}}
            <div x-show="activeTab === 'general'" x-transition>
                {{-- Info-Box: Eigene Rolle und Berechtigungen --}}
            @if($currentUserRole ?? null)
                <div class="mb-4 p-4 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)]">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Deine Rolle im Projekt</h4>
                        <span class="text-sm font-medium px-2 py-1 rounded bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                            {{ ucfirst($currentUserRole) }}
                        </span>
                    </div>
                    <div class="text-xs text-[var(--ui-muted)] space-y-1">
                        @if($currentUserRole === 'owner')
                            <p>✓ Du hast vollen Zugriff auf alle Projekt-Funktionen</p>
                            <p>✓ Du kannst das Projekt löschen und Ownership übertragen</p>
                        @elseif($currentUserRole === 'admin')
                            <p>✓ Du kannst Projektdetails bearbeiten</p>
                            <p>✓ Du kannst Mitglieder einladen und entfernen</p>
                            <p>✗ Du kannst keine Rollen ändern oder Ownership übertragen</p>
                        @elseif($currentUserRole === 'member')
                            <p>✓ Du kannst Projektdetails bearbeiten</p>
                            <p>✓ Du kannst Aufgaben erstellen und bearbeiten</p>
                            <p>✗ Du kannst keine Mitglieder verwalten</p>
                        @elseif($currentUserRole === 'viewer')
                            <p>✓ Du kannst das Projekt und Aufgaben ansehen</p>
                            <p>✗ Du kannst keine Änderungen vornehmen</p>
                        @endif
                    </div>
                </div>
            @endif

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

                @can('update', $project)
                    <x-ui-input-text 
                        name="project.planned_minutes"
                        label="Geplante Minuten"
                        type="number"
                        min="0"
                        step="15"
                        wire:model.live.debounce.500ms="project.planned_minutes"
                        placeholder="z. B. 480 für 8 Stunden"
                        :errorKey="'project.planned_minutes'"
                    />

                    <x-ui-input-text 
                        name="project.customer_cost_center"
                        label="Kostenstelle"
                        wire:model.live.debounce.500ms="project.customer_cost_center"
                        placeholder="Kostenstelle hinterlegen"
                        :errorKey="'project.customer_cost_center'"
                    />
                @else
                    <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                        <span class="text-[var(--ui-muted)]">Kostenstelle</span>
                        <span class="font-medium text-[var(--ui-body-color)]">{{ $project->customer_cost_center ?? '–' }}</span>
                    </div>
                @endcan
            </x-ui-form-grid>

            <x-ui-form-grid :cols="1" :gap="6">
                {{-- Projekttyp Toggle --}}
                @php $ptype = ($project->project_type?->value ?? $project->project_type); @endphp
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-[var(--ui-body-color)]">Projekttyp</label>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            wire:click="setProjectType('internal')"
                            class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors border-2 {{ $projectType === 'internal' ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]' : 'bg-white text-[var(--ui-secondary)] border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}"
                        >
                            Intern
                        </button>
                        <button
                            type="button"
                            wire:click="setProjectType('customer')"
                            class="flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors border-2 {{ $projectType === 'customer' ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]' : 'bg-white text-[var(--ui-secondary)] border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}"
                        >
                            Kunde
                        </button>
                    </div>
                    <div class="text-xs text-[var(--ui-muted)]">
                        {{ $ptype==='customer' ? 'Typ: Kunde (nicht zurücksetzbar)' : 'Typ: Intern' }}
                    </div>
                </div>

                {{-- Kundenprojekt: Hinweis --}}
                @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                    <x-ui-info-banner
                        message="Kundenprojekt wurde angelegt. Verwende den 'Kunden'-Button in der Projekt-Ansicht, um die Firma zu verknüpfen."
                        variant="info"
                    />
                @endif

                {{-- Beteiligte Benutzer --}}
                <div class="space-y-4">
                    <h3 class="text-lg font-medium">Projekt-Teilnehmer</h3>
                    
                    {{-- Aktuelle Teilnehmer --}}
                    <div class="space-y-2">
                        @foreach($project->projectUsers as $projectUser)
                            @if($projectUser->user)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        @if($projectUser->user->avatar)
                                            <img src="{{ $projectUser->user->avatar }}" alt="{{ $projectUser->user->name }}" class="w-8 h-8 rounded-full object-cover">
                                        @else
                                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-medium">
                                                {{ substr($projectUser->user->name, 0, 1) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium">{{ $projectUser->user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $projectUser->user->email }}</div>
                                        </div>
                                    </div>
                                
                            <div class="flex items-center space-x-2">
                                {{-- Rolle ändern --}}
                                @can('changeRole', $project)
                                    <x-ui-input-select
                                        name="role_{{ $projectUser->user_id }}"
                                        :options="collect(\Platform\Planner\Enums\ProjectRole::cases())->map(fn($r)=>['value'=>$r->value,'label'=>ucfirst($r->value)])"
                                        wire:change="changeUserRole({{ $projectUser->user_id }}, $event.target.value)"
                                        :nullable="false"
                                        :disabled="$projectUser->role === \Platform\Planner\Enums\ProjectRole::OWNER->value"
                                        :value="$projectUser->role"
                                        size="sm"
                                    />
                                @else
                                    <span class="text-sm font-medium px-2 py-1 bg-[var(--ui-muted-5)] rounded">
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
                            @endif
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
                                        <div class="flex items-center justify-between p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                                            <div class="flex items-center space-x-3">
                                                @if($user->avatar)
                                                    <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="w-6 h-6 rounded-full object-cover">
                                                @else
                                                    <div class="w-6 h-6 bg-[var(--ui-primary-5)] text-[var(--ui-primary)] rounded-full flex items-center justify-center text-xs font-medium">
                                                        {{ substr($user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="font-medium text-sm text-[var(--ui-secondary)]">{{ $user->name }}</div>
                                                    <div class="text-xs text-[var(--ui-muted)]">{{ $user->email }}</div>
                                                </div>
                                            </div>
                                            <x-ui-button variant="secondary" size="xs" wire:click="addProjectUser({{ $user->id }}, 'member')">Hinzufügen</x-ui-button>
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
                                    @if($projectUser->user && $projectUser->role !== \Platform\Planner\Enums\ProjectRole::OWNER->value)
                                        <option value="{{ $projectUser->user_id }}">
                                            {{ $projectUser->user->name }} ({{ ucfirst($projectUser->role) }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    @endcan
                </div>
                
                {{-- Projekt abschließen --}}
                @can('update', $project)
                    @if(!$project->done)
                        <div class="border-t pt-4">
                            <x-ui-button 
                                variant="success" 
                                wire:click="markAsDone"
                                class="w-full"
                            >
                                <span class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-check-circle','w-5 h-5')
                                    <span>Projekt abschließen</span>
                                </span>
                            </x-ui-button>
                        </div>
                    @else
                        <div class="border-t pt-4">
                            <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center gap-2 text-green-700">
                                    @svg('heroicon-o-check-circle','w-5 h-5')
                                    <span class="font-medium">Projekt abgeschlossen</span>
                                </div>
                                @if($project->done_at)
                                    <p class="text-sm text-green-600 mt-1">
                                        Abgeschlossen am: {{ $project->done_at->format('d.m.Y H:i') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif
                @endcan
                
                {{-- Projekt löschen --}}
                @can('delete', $project)
                        <x-ui-confirm-button action="deleteProject" text="Projekt löschen" confirmText="Wirklich löschen?" />
                @endcan
            </x-ui-form-grid>
            </div>

            {{-- Tab: Wiederkehrende Aufgaben --}}
            <div x-show="activeTab === 'recurring'" x-transition>
                <livewire:planner.recurring-tasks-tab :project-id="$project->id" />
            </div>
        @endif

        <x-slot name="footer">
            @if($project)
                <div x-show="activeTab === 'general'">
                    @can('update', $project)
                        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
                    @endcan
                </div>
            @endif
        </x-slot>
    </x-ui-modal>
</div>