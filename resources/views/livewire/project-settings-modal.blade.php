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
                        label="Kostenstelle (Kunde)"
                        wire:model.live.debounce.500ms="project.customer_cost_center"
                        placeholder="Kostenstelle hinterlegen"
                        :errorKey="'project.customer_cost_center'"
                    />
                @else
                    <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                        <span class="text-[var(--ui-muted)]">Geplante Minuten</span>
                        <span class="font-medium text-[var(--ui-body-color)]">{{ $project->planned_minutes ?? '–' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                        <span class="text-[var(--ui-muted)]">Kostenstelle</span>
                        <span class="font-medium text-[var(--ui-body-color)]">{{ $project->customer_cost_center ?? '–' }}</span>
                    </div>
                @endcan
            </x-ui-form-grid>

            <x-ui-form-grid :cols="1" :gap="6">
                {{-- Projekttyp Toggle (lokal) --}}
                @php $ptype = ($project->project_type?->value ?? $project->project_type); @endphp
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-[var(--ui-body-color)]">Projekttyp</label>
                    <x-ui-segmented-toggle 
                        model="projectType"
                        :current="$ptype"
                        :options="[
                            ['value' => 'internal', 'label' => 'Intern'],
                            ['value' => 'customer', 'label' => 'Kunde'],
                        ]"
                        size="sm"
                        active-variant="primary"
                        wire:change="setProjectType($event.detail.value)"
                    />
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
                                                <div class="w-6 h-6 bg-[var(--ui-primary-5)] text-[var(--ui-primary)] rounded-full flex items-center justify-center text-xs font-medium">
                                                    {{ substr($user->name, 0, 1) }}
                                                </div>
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