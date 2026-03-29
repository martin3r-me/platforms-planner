<div x-data="{ activeTab: @entangle('activeTab') }">
    <x-ui-modal size="lg" model="modalShow" header="Project Settings">
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
                    @click="activeTab = 'billing'"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                    :class="activeTab === 'billing' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                >
                    Abrechnung
                </button>
                <button
                    type="button"
                    @click="activeTab = 'tags'"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                    :class="activeTab === 'tags' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                >
                    Tags & Farbe
                </button>
                <button
                    type="button"
                    @click="activeTab = 'recurring'"
                    class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                    :class="activeTab === 'recurring' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                >
                    Wiederkehrende Aufgaben
                </button>
                @can('update', $project)
                    <button
                        type="button"
                        @click="activeTab = 'canvases'"
                        class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                        :class="activeTab === 'canvases' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
                    >
                        Canvases
                    </button>
                @endcan
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
                            <p>Du hast vollen Zugriff auf alle Projekt-Funktionen</p>
                            <p>Du kannst das Projekt loeschen und Ownership uebertragen</p>
                        @elseif($currentUserRole === 'admin')
                            <p>Du kannst Projektdetails bearbeiten</p>
                            <p>Du kannst Mitglieder einladen und entfernen</p>
                        @elseif($currentUserRole === 'member')
                            <p>Du kannst Projektdetails bearbeiten</p>
                            <p>Du kannst Aufgaben erstellen und bearbeiten</p>
                        @elseif($currentUserRole === 'viewer')
                            <p>Du kannst das Projekt und Aufgaben ansehen</p>
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
                        placeholder="z. B. 480 fuer 8 Stunden"
                        :errorKey="'project.planned_minutes'"
                    />
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
                        @if($ptype === 'customer')
                            Typ: Kunde (nicht zuruecksetzbar)
                        @else
                            Typ: Intern
                        @endif
                    </div>
                </div>

                {{-- Verknuepfte Entities (read-only) --}}
                @if(!empty($entityLinks))
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-[var(--ui-body-color)]">Verknuepfte Entities</label>
                        <div class="space-y-1">
                            @foreach($entityLinks as $link)
                                <div class="flex items-center gap-2 p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                    @svg('heroicon-o-rectangle-group', 'w-4 h-4 text-[var(--ui-muted)]')
                                    <span class="text-sm text-[var(--ui-secondary)]">{{ $link['entity_name'] }}</span>
                                    @if($link['entity_type'])
                                        <span class="text-xs text-[var(--ui-muted)]">({{ $link['entity_type'] }})</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-[var(--ui-muted)]">Entity-Verknuepfungen werden ueber die Projekt-Ansicht verwaltet.</p>
                    </div>
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
                                {{-- Rolle aendern --}}
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

                    {{-- Neuen Teilnehmer hinzufuegen --}}
                    @can('invite', $project)
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium mb-3">Teilnehmer hinzufuegen</h4>

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
                                            <x-ui-button variant="secondary" size="xs" wire:click="addProjectUser({{ $user->id }}, 'member')">Hinzufuegen</x-ui-button>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-500">Alle Team-Mitglieder sind bereits im Projekt.</p>
                            @endif
                        </div>
                    @endcan

                    {{-- Ownership uebertragen --}}
                    @can('transferOwnership', $project)
                        <div class="border-t pt-4">
                            <h4 class="text-md font-medium mb-3 text-orange-600">Ownership uebertragen</h4>
                            <p class="text-sm text-gray-600 mb-3">Vorsicht: Dies uebertraegt die Projektleitung an einen anderen User.</p>

                            <select
                                wire:change="transferOwnership($event.target.value)"
                                class="w-full border rounded px-3 py-2"
                            >
                                <option value="">Ownership uebertragen an...</option>
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

                {{-- Projekt abschliessen --}}
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
                                    <span>Projekt abschliessen</span>
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

                {{-- Projekt loeschen --}}
                @can('delete', $project)
                        <x-ui-confirm-button action="deleteProject" text="Projekt loeschen" confirmText="Wirklich loeschen?" />
                @endcan
            </x-ui-form-grid>
            </div>

            {{-- Tab: Abrechnung --}}
            <div x-show="activeTab === 'billing'" x-transition>
                <div class="space-y-6">
                    @can('update', $project)
                        <div>
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Abrechnung</h3>
                            <x-ui-form-grid :cols="2" :gap="3">
                                <x-ui-input-select
                                    name="project.billing_method"
                                    label="Abrechnungsmethode"
                                    :options="$billingMethodOptions"
                                    wire:model.live="project.billing_method"
                                    nullable="true"
                                    nullLabel="-- waehlen --"
                                />

                                <x-ui-input-text
                                    name="project.hourly_rate"
                                    label="Stundensatz"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live.debounce.500ms="project.hourly_rate"
                                    placeholder="z.B. 120.00"
                                />

                                <x-ui-input-text
                                    name="project.budget_amount"
                                    label="Budget"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    wire:model.live.debounce.500ms="project.budget_amount"
                                    placeholder="z.B. 10000.00"
                                />

                                <x-ui-input-text
                                    name="project.currency"
                                    label="Waehrung"
                                    wire:model.live.debounce.500ms="project.currency"
                                    placeholder="EUR"
                                    maxlength="3"
                                />
                            </x-ui-form-grid>
                        </div>
                    @else
                        <div class="space-y-2">
                            @if($project->billing_method)
                                <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                                    <span class="text-[var(--ui-muted)]">Abrechnungsmethode</span>
                                    <span class="font-medium text-[var(--ui-body-color)]">{{ $project->billing_method?->value ?? $project->billing_method }}</span>
                                </div>
                            @endif
                            @if($project->hourly_rate)
                                <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                                    <span class="text-[var(--ui-muted)]">Stundensatz</span>
                                    <span class="font-medium text-[var(--ui-body-color)]">{{ number_format($project->hourly_rate, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</span>
                                </div>
                            @endif
                            @if($project->budget_amount)
                                <div class="flex items-center justify-between text-sm p-2 rounded border border-[var(--ui-border)] bg-white">
                                    <span class="text-[var(--ui-muted)]">Budget</span>
                                    <span class="font-medium text-[var(--ui-body-color)]">{{ number_format($project->budget_amount, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</span>
                                </div>
                            @endif
                        </div>
                    @endcan
                </div>
            </div>

            {{-- Tab: Tags & Farbe --}}
            <div x-show="activeTab === 'tags'" x-transition>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2">Projekt-Tags & Farbe</h3>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">Tags und Farben fuer dieses Projekt verwalten. Tags helfen bei der Kategorisierung und Filterung.</p>
                    </div>
                    <livewire:core.inline-tags
                        :context-type="get_class($project)"
                        :context-id="$project->id"
                        :key="'inline-tags-project-' . $project->id"
                    />
                </div>
            </div>

            {{-- Tab: Wiederkehrende Aufgaben --}}
            <div x-show="activeTab === 'recurring'" x-transition>
                <livewire:planner.recurring-tasks-tab :project-id="$project->id" />
            </div>

            {{-- Tab: Canvases --}}
            @can('update', $project)
                <div x-show="activeTab === 'canvases'" x-transition>
                    <div class="space-y-6">
                        {{-- Verknuepfte Canvases --}}
                        <div>
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Verknuepfte Canvases</h3>
                            @if(!empty($linkedCanvases))
                                <div class="space-y-2">
                                    @foreach($linkedCanvases as $canvas)
                                        <div class="flex items-center justify-between p-3 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                            <div class="flex items-center gap-3">
                                                @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)]')
                                                <div>
                                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $canvas['name'] }}</div>
                                                    <div class="text-xs text-[var(--ui-muted)]">
                                                        {{ $canvas['type'] === 'pc_canvas' ? 'Project Canvas' : 'Canvas' }}
                                                        @if($canvas['status'])
                                                            &middot; {{ $canvas['status'] }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ $canvas['url'] }}" wire:navigate class="text-[var(--ui-primary)] hover:text-[var(--ui-primary-hover)] transition-colors" title="Canvas oeffnen">
                                                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                                                </a>
                                                <button wire:click="detachCanvas({{ $canvas['id'] }}, '{{ $canvas['type'] }}')" class="text-red-500 hover:text-red-700 transition-colors" title="Verknuepfung entfernen">
                                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-[var(--ui-muted)]">Noch keine Canvases verknuepft.</p>
                            @endif
                        </div>

                        {{-- Canvas hinzufuegen --}}
                        <div class="border-t border-[var(--ui-border)] pt-4">
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Canvas hinzufuegen</h3>
                            <x-ui-input-text
                                name="canvasSearch"
                                wire:model.live.debounce.300ms="canvasSearch"
                                placeholder="Canvas suchen..."
                                :errorKey="'canvasSearch'"
                            />

                            @if(!empty($availableCanvases))
                                <div class="mt-3 space-y-2">
                                    @foreach($availableCanvases as $canvas)
                                        <div class="flex items-center justify-between p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                                            <div class="flex items-center gap-2">
                                                @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-muted)]')
                                                <div>
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $canvas['name'] }}</span>
                                                    <span class="text-xs text-[var(--ui-muted)] ml-1">({{ $canvas['type_label'] }})</span>
                                                </div>
                                            </div>
                                            <x-ui-button variant="secondary" size="xs" wire:click="attachCanvas({{ $canvas['id'] }}, '{{ $canvas['type'] }}')">
                                                Verknuepfen
                                            </x-ui-button>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif(strlen($canvasSearch) >= 2)
                                <p class="mt-3 text-sm text-[var(--ui-muted)]">Keine Canvases gefunden.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endcan
        @endif

        <x-slot name="footer">
            @if($project)
                <div x-show="activeTab === 'general' || activeTab === 'billing'">
                    @can('update', $project)
                        <x-ui-button variant="success" wire:click="save">Speichern</x-ui-button>
                    @endcan
                </div>
            @endif
        </x-slot>
    </x-ui-modal>
</div>
