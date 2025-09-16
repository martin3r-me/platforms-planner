<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        Project Settings
    </x-slot>

    @if($project)
        <div class="flex-grow-1 overflow-y-auto p-4">

            <div class="form-group">
                {{-- Projekt Name --}}
                @can('update', $project)
                    <x-ui-input-text 
                        name="project.name"
                        label="Projekt Name"
                        wire:model.live.debounce.500ms="project.name"
                        placeholder="Projekt Name eingeben..."
                        required
                        :errorKey="'project.name'"
                    />
                @else
                    <div>
                        <label class="block text-sm font-medium">Projekt Name</label>
                        <div class="py-1">{{ $project->name }}</div>
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
                    <div>
                        <label class="block text-sm font-medium">Projekt Beschreibung</label>
                        <div class="py-1">{{ $project->description }}</div>
                    </div>
                @endcan
            </div>

            {{-- Projekttyp Toggle (Buttons) --}}
            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">Projekttyp</label>
                <div class="d-flex gap-2">
                    <x-ui-button 
                        size="sm" 
                        variant="secondary-outline" 
                        @click="$wire.setProjectType('internal')"
                        :disabled="($project->project_type?->value ?? $project->project_type) === 'customer'"
                    >
                        Intern
                    </x-ui-button>
                    <x-ui-button 
                        size="sm" 
                        variant="primary-outline" 
                        @click="$wire.setProjectType('customer')"
                    >
                        Kunde
                    </x-ui-button>
                </div>
                <div class="text-xs text-muted mt-1">
                    @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                        Typ: Kunde (nicht zurücksetzbar)
                    @else
                        Typ: Intern
                    @endif
                </div>
            </div>

            {{-- Kundenprojekt Einstellungen (sichtbar wenn Typ Kunde) --}}
            @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                <hr class="my-4">
                <h4 class="text-sm font-semibold mb-2">Kundenprojekt</h4>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui-input-text 
                        name="customerProjectForm.company_id"
                        label="Company ID (CRM)"
                        wire:model.live.debounce.500ms="customerProjectForm.company_id"
                        placeholder="z.B. 123"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.contact_id"
                        label="Contact ID (CRM)"
                        wire:model.live.debounce.500ms="customerProjectForm.contact_id"
                        placeholder="z.B. 456"
                    />
                    <x-ui-input-select
                        name="customerProjectForm.billing_method"
                        label="Abrechnungsmethode"
                        :options="$billingMethodOptions"
                        optionValue="value"
                        optionLabel="label"
                        :nullable="true"
                        nullLabel="– wählen –"
                        wire:model.live="customerProjectForm.billing_method"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.hourly_rate"
                        type="number"
                        step="0.01"
                        label="Stundensatz"
                        wire:model.live.debounce.500ms="customerProjectForm.hourly_rate"
                        placeholder="z.B. 120.00"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.currency"
                        label="Währung"
                        wire:model.live.debounce.500ms="customerProjectForm.currency"
                        placeholder="EUR"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.budget_amount"
                        type="number"
                        step="0.01"
                        label="Budget"
                        wire:model.live.debounce.500ms="customerProjectForm.budget_amount"
                        placeholder="z.B. 10000.00"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.cost_center"
                        label="Kostenstelle"
                        wire:model.live.debounce.500ms="customerProjectForm.cost_center"
                        placeholder="z.B. KST-1001"
                    />
                    <x-ui-input-text 
                        name="customerProjectForm.invoice_account"
                        label="Sachkonto"
                        wire:model.live.debounce.500ms="customerProjectForm.invoice_account"
                        placeholder="z.B. 8400"
                    />
                </div>
                <div class="mt-3">
                    <x-ui-input-textarea 
                        name="customerProjectForm.notes"
                        label="Notizen"
                        wire:model.live.debounce.500ms="customerProjectForm.notes"
                        placeholder="Interne Hinweise zum Kundenprojekt"
                        rows="3"
                    />
                </div>
            @endif

            {{-- Beteiligte Benutzer --}}
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Projekt-Mitglieder & Rollen</label>
                <div class="space-y-2">
                    @foreach ($teamUsers as $user)
                        @php
                            $projectUser = $project->projectUsers->firstWhere('user_id', $user->id);
                            $role = $projectUser?->role ?? null;
                            $isOwner = $role === \Platform\Planner\Enums\ProjectRole::OWNER->value;
                            $isAssigned = !is_null($role);
                        @endphp

                        <div class="flex items-center gap-2 p-2 rounded @if($isOwner) bg-primary-10 @endif">
                            <x-heroicon-o-user class="w-4 h-4"/>
                            <span class="text-sm font-medium">
                                {{ $user->fullname ?? $user->name }} ({{ $user->email }})
                            </span>

                            @if ($isOwner)
                                <span class="ml-2 px-2 py-1 rounded bg-primary text-white text-xs">Owner</span>
                            @else
                                @can('update', $project)
                                    <select wire:model="roles.{{ $user->id }}" class="border rounded px-2 py-1 text-xs ml-2" style="min-width: 90px;">
                                        <option value="">– Nicht beteiligt –</option>
                                        @foreach(\Platform\Planner\Enums\ProjectRole::cases() as $enumRole)
                                            @if($enumRole->value !== \Platform\Planner\Enums\ProjectRole::OWNER->value)
                                                <option value="{{ $enumRole->value }}">{{ ucfirst($enumRole->value) }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                @else
                                    @if ($isAssigned)
                                        <span class="ml-2 px-2 py-1 rounded bg-slate-200 text-xs">
                                            {{ ucfirst($role) }}
                                        </span>
                                    @endif
                                @endcan
                            @endif

                            {{-- Entfernen-Button (falls nicht Owner und beteiligt) --}}
                            @can('update', $project)
                                @if (!$isOwner && $isAssigned)
                                    <button wire:click="removeProjectUser({{ $user->id }})" class="ml-2 text-red-500 hover:text-red-700" title="Entfernen">
                                        <x-heroicon-o-x-mark class="w-4 h-4"/>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    @endforeach
                </div>
            </div>
            <hr>
            @can('delete', $project)
                <x-ui-confirm-button action="deleteProject" text="Projekt löschen" confirmText="Wirklich löschen?" />
            @endcan
        </div>
    @endif

    <x-slot name="footer">
        @can('update', $project)
            <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
        @endcan
    </x-slot>
</x-ui-modal>