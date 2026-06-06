<div x-data="{ activeTab: @entangle('activeTab') }">
    <x-ui-modal size="lg" model="modalShow">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                    @svg('heroicon-o-cog-6-tooth', 'w-5 h-5 text-[var(--planner-status-active)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">
                        Projekt-Einstellungen
                    </h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5 truncate">
                        @if($project) {{ $project->name }} @endif
                    </p>
                </div>
            </div>
        </x-slot>

        @if($project)
            {{-- TABS (segmented) --}}
            @php
                $tabs = [
                    ['key' => 'general',   'label' => 'Allgemein',     'icon' => 'heroicon-o-adjustments-horizontal'],
                    ['key' => 'billing',   'label' => 'Abrechnung',    'icon' => 'heroicon-o-banknotes'],
                    ['key' => 'recurring', 'label' => 'Wiederkehrend', 'icon' => 'heroicon-o-arrow-path'],
                ];
                $canUpdate = auth()->user()?->can('update', $project) ?? false;
                if ($canUpdate) {
                    $tabs[] = ['key' => 'sharing', 'label' => 'Teilen', 'icon' => 'heroicon-o-share'];
                }
            @endphp
            <div class="flex flex-wrap items-center gap-1 mb-5 p-1 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                @foreach($tabs as $tab)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['key'] }}'"
                        class="inline-flex items-center gap-1.5 px-3 h-7 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === '{{ $tab['key'] }}' ? 'bg-white text-[var(--planner-status-active)] shadow-sm' : 'bg-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'"
                    >
                        @svg($tab['icon'], 'w-3.5 h-3.5')
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- TAB: ALLGEMEIN                                              --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'general'" x-transition class="space-y-5">

                {{-- Rolle im Projekt --}}
                @if($currentUserRole ?? null)
                    <section class="p-3 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--planner-status-active)]/5">
                        <div class="flex items-center justify-between mb-1.5">
                            <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--planner-status-active)] m-0">Deine Rolle</h4>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-active)] text-white uppercase tracking-wider">
                                {{ $currentUserRole }}
                            </span>
                        </div>
                        <p class="text-[11px] text-[var(--ui-secondary)] m-0 leading-snug">
                            @if($currentUserRole === 'owner')
                                Voller Zugriff. Du kannst das Projekt löschen und Ownership übertragen.
                            @elseif($currentUserRole === 'admin')
                                Du kannst Projektdetails bearbeiten und Mitglieder einladen.
                            @elseif($currentUserRole === 'member')
                                Du kannst Projektdetails bearbeiten und Aufgaben verwalten.
                            @elseif($currentUserRole === 'viewer')
                                Nur Lesezugriff auf Projekt und Aufgaben.
                            @endif
                        </p>
                    </section>
                @endif

                {{-- Basis-Daten --}}
                <section class="space-y-3">
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Basis</h4>
                    @can('update', $project)
                        <x-ui-input-text
                            name="project.name"
                            label="Projektname"
                            wire:model.live.debounce.500ms="project.name"
                            placeholder="Projekt Name eingeben..."
                            required
                            :errorKey="'project.name'"
                        />
                        <x-ui-input-textarea
                            name="project.description"
                            label="Beschreibung"
                            wire:model.live.debounce.500ms="project.description"
                            placeholder="Worum geht es in diesem Projekt?"
                            :errorKey="'project.description'"
                        />
                        <x-ui-input-text
                            name="plannedMinutes"
                            label="Geplante Minuten"
                            type="number"
                            min="0"
                            step="15"
                            wire:model.live.debounce.500ms="plannedMinutes"
                            placeholder="z. B. 480 für 8 Stunden"
                            :errorKey="'plannedMinutes'"
                        />
                    @else
                        <dl class="space-y-1.5 text-[12px]">
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--ui-muted-5)]">
                                <dt class="text-[var(--ui-muted)]">Name</dt>
                                <dd class="text-[var(--ui-secondary)] font-medium m-0 truncate">{{ $project->name }}</dd>
                            </div>
                            @if($project->description)
                                <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--ui-muted-5)]">
                                    <dt class="text-[var(--ui-muted)] flex-shrink-0">Beschreibung</dt>
                                    <dd class="text-[var(--ui-secondary)] m-0 text-right">{{ $project->description }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endcan
                </section>

                {{-- Projekttyp --}}
                @php $ptype = ($project->project_type?->value ?? $project->project_type); @endphp
                <section class="space-y-2">
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Projekttyp</h4>
                    <div class="inline-flex rounded-md border border-[var(--ui-border)] overflow-hidden w-full">
                        <button
                            type="button"
                            wire:click="setProjectType('internal')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium transition-colors {{ $projectType === 'internal' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                        >
                            @svg('heroicon-o-building-office', 'w-3.5 h-3.5')
                            Intern
                        </button>
                        <button
                            type="button"
                            wire:click="setProjectType('customer')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium border-l border-[var(--ui-border)] transition-colors {{ $projectType === 'customer' ? 'bg-[var(--planner-status-active)] text-white' : 'bg-transparent text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                        >
                            @svg('heroicon-o-briefcase', 'w-3.5 h-3.5')
                            Kunde
                        </button>
                    </div>
                    @if($ptype === 'customer')
                        <p class="text-[10px] text-[var(--ui-muted)] m-0">Hinweis: Der Kunden-Typ ist nicht zurücksetzbar.</p>
                    @endif
                </section>

                {{-- Verknüpfte Entities --}}
                @if(!empty($entityLinks))
                    <section class="space-y-2">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Verknüpfte Entities</h4>
                        <ul class="space-y-1">
                            @foreach($entityLinks as $link)
                                <li class="flex items-center gap-2 px-2.5 py-1.5 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] text-[12px]">
                                    @svg('heroicon-o-rectangle-group', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                                    <span class="text-[var(--ui-secondary)] font-medium truncate">{{ $link['entity_name'] }}</span>
                                    @if($link['entity_type'])
                                        <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">({{ $link['entity_type'] }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-[10px] text-[var(--ui-muted)] m-0">Verknüpfungen werden über die Projekt-Ansicht verwaltet.</p>
                    </section>
                @endif

                {{-- TEILNEHMER --}}
                <section class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Teilnehmer</h4>
                        <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ $project->projectUsers->where('user', '!=', null)->count() }}</span>
                    </div>

                    <ul class="space-y-1.5">
                        @foreach($project->projectUsers as $projectUser)
                            @if($projectUser->user)
                                @php
                                    $u = $projectUser->user;
                                    $isOwner = $projectUser->role === \Platform\Planner\Enums\ProjectRole::OWNER->value;
                                    $initial = mb_strtoupper(mb_substr($u->name ?? $u->email ?? 'U', 0, 1));
                                @endphp
                                <li class="flex items-center gap-3 px-3 py-2 rounded-lg border border-[var(--ui-border)]/40 bg-white">
                                    @if($u->avatar)
                                        <img src="{{ $u->avatar }}" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                                    @else
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-[var(--ui-secondary)] text-white text-[12px] font-semibold flex-shrink-0">{{ $initial }}</span>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-medium text-[var(--ui-secondary)] truncate">{{ $u->name }}</div>
                                        <div class="text-[11px] text-[var(--ui-muted)] truncate">{{ $u->email }}</div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        @if($isOwner)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-700 uppercase tracking-wider">
                                                @svg('heroicon-o-star', 'w-3 h-3')
                                                Owner
                                            </span>
                                        @else
                                            @can('changeRole', $project)
                                                <x-ui-input-select
                                                    name="role_{{ $projectUser->user_id }}"
                                                    :options="collect(\Platform\Planner\Enums\ProjectRole::cases())->map(fn($r)=>['value'=>$r->value,'label'=>ucfirst($r->value)])"
                                                    wire:change="changeUserRole({{ $projectUser->user_id }}, $event.target.value)"
                                                    :nullable="false"
                                                    :value="$projectUser->role"
                                                    size="sm"
                                                />
                                            @else
                                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)]">
                                                    {{ ucfirst($projectUser->role) }}
                                                </span>
                                            @endcan
                                            @can('removeMember', $project)
                                                <button
                                                    wire:click="removeProjectUser({{ $projectUser->user_id }})"
                                                    wire:confirm="Mitglied wirklich entfernen?"
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--planner-status-overdue)] hover:bg-[var(--planner-status-overdue)]/5 transition-colors"
                                                    title="Entfernen"
                                                >
                                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </li>
                            @endif
                        @endforeach
                    </ul>

                    {{-- Neuen Teilnehmer hinzufügen --}}
                    @can('invite', $project)
                        @php $availableUsers = $this->getAvailableUsers(); @endphp
                        @if($availableUsers->count() > 0)
                            <details class="rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/40 group">
                                <summary class="px-3 py-2 text-[11px] font-medium text-[var(--ui-secondary)] cursor-pointer inline-flex items-center gap-1.5 list-none">
                                    @svg('heroicon-o-plus-circle', 'w-3.5 h-3.5 text-[var(--planner-status-active)]')
                                    <span>Teilnehmer hinzufügen</span>
                                    <span class="ml-1 text-[10px] text-[var(--ui-muted)] tabular-nums">{{ $availableUsers->count() }} verfügbar</span>
                                </summary>
                                <ul class="px-2 pb-2 space-y-1">
                                    @foreach($availableUsers as $user)
                                        @php $aInitial = mb_strtoupper(mb_substr($user->name ?? $user->email ?? 'U', 0, 1)); @endphp
                                        <li class="flex items-center gap-2 px-2 py-1.5 rounded bg-white">
                                            @if($user->avatar)
                                                <img src="{{ $user->avatar }}" alt="" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                                            @else
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-[var(--ui-secondary)] text-white text-[10px] font-semibold flex-shrink-0">{{ $aInitial }}</span>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <div class="text-[12px] font-medium text-[var(--ui-secondary)] truncate">{{ $user->name }}</div>
                                                <div class="text-[10px] text-[var(--ui-muted)] truncate">{{ $user->email }}</div>
                                            </div>
                                            <x-ui-button variant="secondary" size="xs" wire:click="addProjectUser({{ $user->id }}, 'member')">
                                                Hinzufügen
                                            </x-ui-button>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <p class="text-[11px] text-[var(--ui-muted)] m-0">Alle Team-Mitglieder sind bereits im Projekt.</p>
                        @endif
                    @endcan

                    {{-- Ownership übertragen --}}
                    @can('transferOwnership', $project)
                        <details class="rounded-lg border border-amber-200 bg-amber-50/60 group">
                            <summary class="px-3 py-2 text-[11px] font-medium text-amber-700 cursor-pointer inline-flex items-center gap-1.5 list-none">
                                @svg('heroicon-o-star', 'w-3.5 h-3.5')
                                <span>Ownership übertragen</span>
                            </summary>
                            <div class="px-3 pb-3 space-y-2">
                                <p class="text-[11px] text-amber-700/80 m-0">
                                    Vorsicht: Du verlierst danach die Owner-Rechte. Diese Aktion ist nicht rückgängig zu machen.
                                </p>
                                <select
                                    wire:change="transferOwnership($event.target.value)"
                                    class="w-full text-[12px] border border-amber-300 rounded-md px-2.5 py-1.5 bg-white text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-amber-300/40"
                                >
                                    <option value="">Übertragen an...</option>
                                    @foreach($project->projectUsers as $projectUser)
                                        @if($projectUser->user && $projectUser->role !== \Platform\Planner\Enums\ProjectRole::OWNER->value)
                                            <option value="{{ $projectUser->user_id }}">
                                                {{ $projectUser->user->name }} ({{ ucfirst($projectUser->role) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </details>
                    @endcan
                </section>

                {{-- Status / Lifecycle --}}
                @can('update', $project)
                    <section class="space-y-2 pt-3 border-t border-[var(--ui-border)]/40">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Projekt-Status</h4>
                        @if(!$project->done)
                            <button
                                type="button"
                                wire:click="markAsDone"
                                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-[var(--planner-status-done)]/40 bg-[var(--planner-status-done)]/5 text-[var(--planner-status-done)] hover:bg-[var(--planner-status-done)]/10 transition-colors"
                            >
                                @svg('heroicon-o-check-circle', 'w-4 h-4')
                                Projekt abschließen
                            </button>
                        @else
                            <div class="px-3 py-2.5 rounded-lg border border-[var(--planner-status-done)]/30 bg-[var(--planner-status-done)]/5">
                                <div class="flex items-center gap-2 text-[var(--planner-status-done)]">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                    <span class="text-[12px] font-semibold">Projekt abgeschlossen</span>
                                </div>
                                @if($project->done_at)
                                    <p class="text-[11px] text-[var(--planner-status-done)]/80 mt-1 m-0">am {{ $project->done_at->format('d.m.Y H:i') }}</p>
                                @endif
                            </div>
                        @endif
                    </section>
                @endcan

                {{-- Danger zone --}}
                @can('delete', $project)
                    <section class="space-y-2 pt-3 border-t border-[var(--ui-border)]/40">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--planner-status-overdue)] m-0">Gefahrenzone</h4>
                        <x-ui-confirm-button
                            action="deleteProject"
                            text="Projekt löschen"
                            confirmText="Wirklich löschen?"
                            variant="danger"
                        />
                    </section>
                @endcan
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- TAB: ABRECHNUNG                                             --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'billing'" x-transition class="space-y-4">
                @can('update', $project)
                    <section class="space-y-3">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Abrechnung</h4>
                        <x-ui-form-grid :cols="2" :gap="3">
                            <x-ui-input-select
                                name="project.billing_method"
                                label="Abrechnungsmethode"
                                :options="$billingMethodOptions"
                                wire:model.live="project.billing_method"
                                nullable="true"
                                nullLabel="– wählen –"
                            />
                            <x-ui-input-text
                                name="project.hourly_rate"
                                label="Stundensatz"
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live.debounce.500ms="project.hourly_rate"
                                placeholder="z. B. 120.00"
                            />
                            <x-ui-input-text
                                name="project.budget_amount"
                                label="Budget"
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live.debounce.500ms="project.budget_amount"
                                placeholder="z. B. 10000.00"
                            />
                            <x-ui-input-text
                                name="project.currency"
                                label="Währung"
                                wire:model.live.debounce.500ms="project.currency"
                                placeholder="EUR"
                                maxlength="3"
                            />
                        </x-ui-form-grid>
                    </section>
                @else
                    <section class="space-y-1.5 text-[12px]">
                        @if($project->billing_method)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--ui-muted-5)]">
                                <dt class="text-[var(--ui-muted)]">Abrechnungsmethode</dt>
                                <dd class="text-[var(--ui-secondary)] font-medium m-0">{{ $project->billing_method?->value ?? $project->billing_method }}</dd>
                            </div>
                        @endif
                        @if($project->hourly_rate)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--ui-muted-5)]">
                                <dt class="text-[var(--ui-muted)]">Stundensatz</dt>
                                <dd class="text-[var(--ui-secondary)] font-medium tabular-nums m-0">{{ number_format($project->hourly_rate, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</dd>
                            </div>
                        @endif
                        @if($project->budget_amount)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--ui-muted-5)]">
                                <dt class="text-[var(--ui-muted)]">Budget</dt>
                                <dd class="text-[var(--ui-secondary)] font-medium tabular-nums m-0">{{ number_format($project->budget_amount, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</dd>
                            </div>
                        @endif
                    </section>
                @endcan
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- TAB: WIEDERKEHRENDE AUFGABEN                                --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'recurring'" x-transition>
                <livewire:planner.recurring-tasks-tab :project-id="$project->id" />
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- TAB: TEILEN                                                 --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            @can('update', $project)
                <div x-show="activeTab === 'sharing'" x-transition class="space-y-4">
                    <section class="space-y-3">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Öffentlicher Link</h4>
                        <p class="text-[12px] text-[var(--ui-muted)] m-0">
                            Teile das Projekt-Board per Link. Jeder mit dem Link kann das Board read-only ansehen — ohne Login.
                        </p>

                        @if($isPublic && $publicUrl)
                            <div class="flex items-center gap-2 p-3 rounded-lg border border-[var(--planner-status-done)]/30 bg-[var(--planner-status-done)]/5">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--planner-status-done)] flex-shrink-0')
                                <span class="text-[12px] font-medium text-[var(--planner-status-done)]">Öffentlicher Link ist aktiv</span>
                            </div>

                            <div x-data="{ copied: false }">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1.5">Link</label>
                                <div class="flex gap-1.5">
                                    <input
                                        type="text"
                                        value="{{ $publicUrl }}"
                                        readonly
                                        class="flex-1 px-2.5 py-1.5 text-[12px] bg-[var(--ui-muted-5)] border border-[var(--ui-border)] rounded-md text-[var(--ui-secondary)] select-all focus:outline-none focus:ring-2 focus:ring-[var(--planner-status-active)]/20 tabular-nums"
                                    />
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="inline-flex items-center gap-1.5 px-3 text-[12px] font-medium rounded-md border transition-colors"
                                        :class="copied ? 'bg-[var(--planner-status-done)]/10 border-[var(--planner-status-done)]/30 text-[var(--planner-status-done)]' : 'bg-white border-[var(--ui-border)] text-[var(--ui-secondary)] hover:border-[var(--planner-status-active)]/60'"
                                    >
                                        <template x-if="!copied">
                                            <span class="inline-flex items-center gap-1.5">
                                                @svg('heroicon-o-clipboard-document', 'w-3.5 h-3.5')
                                                Kopieren
                                            </span>
                                        </template>
                                        <template x-if="copied">
                                            <span class="inline-flex items-center gap-1.5">
                                                @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                                Kopiert
                                            </span>
                                        </template>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <x-ui-button variant="secondary-outline" size="sm" wire:click="regeneratePublicLink">
                                    @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                    <span>Neuen Link generieren</span>
                                </x-ui-button>
                                <x-ui-button variant="danger" size="sm" wire:click="disablePublicLink">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                    <span>Deaktivieren</span>
                                </x-ui-button>
                            </div>
                            <p class="text-[10px] text-[var(--ui-muted)] m-0">Beim Generieren wird der alte Link ungültig.</p>
                        @else
                            <div class="flex items-center gap-2 p-3 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                @svg('heroicon-o-lock-closed', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                <span class="text-[12px] text-[var(--ui-muted)]">Kein öffentlicher Link aktiv</span>
                            </div>
                            <x-ui-button variant="primary" size="sm" wire:click="enablePublicLink">
                                @svg('heroicon-o-link', 'w-3.5 h-3.5')
                                <span>Öffentlichen Link erstellen</span>
                            </x-ui-button>
                        @endif
                    </section>
                </div>
            @endcan
        @endif

        <x-slot name="footer">
            @if($project)
                <div x-show="activeTab === 'general' || activeTab === 'billing'" class="flex justify-end gap-2">
                    @can('update', $project)
                        <x-ui-button variant="primary" size="sm" wire:click="save">
                            @svg('heroicon-o-check', 'w-3.5 h-3.5')
                            <span>Speichern</span>
                        </x-ui-button>
                    @endcan
                </div>
            @endif
        </x-slot>
    </x-ui-modal>
</div>
