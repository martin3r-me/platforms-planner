<div x-data="{ activeTab: @entangle('activeTab') }">
    <x-nx-modal size="lg" model="modalShow">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                    @svg('heroicon-o-cog-6-tooth', 'w-5 h-5 text-[var(--nx-accent)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">
                        Projekt-Einstellungen
                    </h3>
                    <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5 truncate">
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
            <div class="flex flex-wrap items-center gap-1 mb-5 p-1 rounded-lg bg-[var(--nx-bg)] border border-[color:var(--nx-line)]">
                @foreach($tabs as $tab)
                    <button
                        type="button"
                        @click="activeTab = '{{ $tab['key'] }}'"
                        class="inline-flex items-center gap-1.5 px-3 h-7 text-xs font-medium rounded-md transition-colors"
                        :class="activeTab === '{{ $tab['key'] }}' ? 'bg-[color:var(--nx-surface)] text-[var(--nx-accent)] shadow-[var(--nx-shadow-card)]' : 'bg-transparent text-[var(--nx-muted)] hover:text-[var(--nx-text)]'"
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
                    <section class="p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-accent)]/5">
                        <div class="flex items-center justify-between mb-1.5">
                            <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-accent)] m-0">Deine Rolle</h4>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--nx-accent)] text-white uppercase tracking-wider">
                                {{ $currentUserRole }}
                            </span>
                        </div>
                        <p class="text-[11px] text-[var(--nx-text)] m-0 leading-snug">
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
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Basis</h4>
                    @can('update', $project)
                        <x-nx-input-text
                            name="project.name"
                            label="Projektname"
                            wire:model.live.debounce.500ms="project.name"
                            placeholder="Projekt Name eingeben..."
                            required
                            :errorKey="'project.name'"
                        />
                        <x-nx-input-textarea
                            name="project.description"
                            label="Beschreibung"
                            wire:model.live.debounce.500ms="project.description"
                            placeholder="Worum geht es in diesem Projekt?"
                            :errorKey="'project.description'"
                        />
                        <x-nx-input-text
                            name="plannedMinutes"
                            label="Geplante Minuten"
                            type="number"
                            min="0"
                            step="15"
                            wire:model.live.debounce.500ms="plannedMinutes"
                            placeholder="z. B. 480 für 8 Stunden"
                            :errorKey="'plannedMinutes'"
                        />
                        <label class="flex items-start gap-2.5 cursor-pointer rounded border border-[var(--nx-border)] p-2.5 hover:bg-[var(--nx-bg)] transition-colors">
                            <input type="checkbox" wire:model="project.require_triage"
                                   class="mt-0.5 h-4 w-4 rounded border-[var(--nx-border)]">
                            <span>
                                <span class="block text-[12px] font-medium text-[var(--nx-text)]">✅ Triage-Pflicht</span>
                                <span class="block text-[11px] text-[var(--nx-muted)]">Tasks dieses Projekts werden erst nach einem Reife-Check (Story-Points + Inhalt) durch die Triage-Rolle bearbeitet. Für sauber gepflegte Projekte aus lassen.</span>
                            </span>
                        </label>
                    @else
                        <dl class="space-y-1.5 text-[12px]">
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--nx-bg)]">
                                <dt class="text-[var(--nx-muted)]">Name</dt>
                                <dd class="text-[var(--nx-text)] font-medium m-0 truncate">{{ $project->name }}</dd>
                            </div>
                            @if($project->description)
                                <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--nx-bg)]">
                                    <dt class="text-[var(--nx-muted)] flex-shrink-0">Beschreibung</dt>
                                    <dd class="text-[var(--nx-text)] m-0 text-right">{{ $project->description }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endcan
                </section>

                {{-- Projekttyp --}}
                @php $ptype = ($project->project_type?->value ?? $project->project_type); @endphp
                <section class="space-y-2">
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Projekttyp</h4>
                    <div class="inline-flex rounded-md border border-[color:var(--nx-line)] overflow-hidden w-full">
                        <button
                            type="button"
                            wire:click="setProjectType('internal')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium transition-colors {{ $projectType === 'internal' ? 'bg-[var(--nx-accent)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                        >
                            @svg('heroicon-o-building-office', 'w-3.5 h-3.5')
                            Intern
                        </button>
                        <button
                            type="button"
                            wire:click="setProjectType('customer')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium border-l border-[color:var(--nx-line)] transition-colors {{ $projectType === 'customer' ? 'bg-[var(--nx-accent)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                        >
                            @svg('heroicon-o-briefcase', 'w-3.5 h-3.5')
                            Kunde
                        </button>
                    </div>
                    @if($ptype === 'customer')
                        <p class="text-[10px] text-[var(--nx-muted)] m-0">Hinweis: Der Kunden-Typ ist nicht zurücksetzbar.</p>
                    @endif
                </section>

                {{-- Wesensart (kind) --}}
                @php $kindVal = ($project->kind?->value ?? $project->kind); @endphp
                <section class="space-y-2">
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Wesensart</h4>
                    <div class="inline-flex rounded-md border border-[color:var(--nx-line)] overflow-hidden w-full">
                        <button
                            type="button"
                            wire:click="setKind('project')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium transition-colors {{ $kindVal === 'project' ? 'bg-[var(--nx-accent)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                            title="Abgegrenzt, hat Ziel und Ende"
                        >
                            @svg('heroicon-o-flag', 'w-3.5 h-3.5')
                            Project
                        </button>
                        <button
                            type="button"
                            wire:click="setKind('run')"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 h-8 text-xs font-medium border-l border-[color:var(--nx-line)] transition-colors {{ $kindVal === 'run' ? 'bg-[var(--nx-accent)] text-white' : 'bg-transparent text-[var(--nx-text)] hover:bg-[var(--nx-bg)]' }}"
                            title="Laeuft fortlaufend, wird nie fertig"
                        >
                            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                            Run
                        </button>
                    </div>
                </section>

                {{-- Lebenszyklus --}}
                @php
                    $lc = $project->lifecycle_state?->value ?? 'aktiv';
                    $lcTones = [
                        'aktiv'         => ['label' => 'Aktiv',         'chip' => 'bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] border-[var(--nx-success)]/30', 'dot' => 'bg-[color:var(--nx-success)]'],
                        'ruhend'        => ['label' => 'Ruhend',        'chip' => 'bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)] border-[var(--nx-warning)]/30',       'dot' => 'bg-[color:var(--nx-warning)]'],
                        'abgeschlossen' => ['label' => 'Abgeschlossen', 'chip' => 'bg-[var(--nx-info)]/10 text-[color:var(--nx-info)] border-[var(--nx-info)]/30',          'dot' => 'bg-[color:var(--nx-info)]'],
                        'verworfen'     => ['label' => 'Verworfen',     'chip' => 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] border-[color:var(--nx-line)]',         'dot' => 'bg-[color:var(--nx-muted)]'],
                    ];
                    $lcMeta = $lcTones[$lc] ?? $lcTones['aktiv'];
                    $lcChangedAt = $project->lifecycle_state_changed_at;
                @endphp
                <section class="space-y-2">
                    <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Lebenszyklus</h4>

                    {{-- Aktueller Zustand --}}
                    <div class="flex items-center gap-2 px-3 py-2 rounded-md border {{ $lcMeta['chip'] }}">
                        <span class="w-2 h-2 rounded-full {{ $lcMeta['dot'] }} flex-shrink-0"></span>
                        <span class="text-sm font-medium">{{ $lcMeta['label'] }}</span>
                        @if($lcChangedAt)
                            <span class="ml-auto text-[10px] opacity-70">seit {{ $lcChangedAt->diffForHumans() }}</span>
                        @endif
                    </div>

                    {{-- Transitions je nach Zustand --}}
                    <div class="flex flex-wrap gap-1.5">
                        @if(in_array($lc, ['aktiv', 'ruhend'], true))
                            <button
                                type="button"
                                wire:click="completeProject"
                                class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-info)]/30 bg-[var(--nx-info)]/10 text-[color:var(--nx-info)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-info)]/10"
                                title="Ziel erreicht, Projekt read-only"
                            >
                                @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                Abschließen
                            </button>
                            <button
                                type="button"
                                wire:click="discardProject"
                                wire:confirm="Wirklich verwerfen? Offene Aufgaben werden kaskadiert."
                                class="inline-flex items-center gap-1 rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] text-[color:var(--nx-text)] px-2.5 py-1 text-[11px] font-medium hover:bg-[color:var(--nx-line)]"
                                title="Ohne Ergebnis beenden (Kaskade: offene Tasks)"
                            >
                                @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5')
                                Verwerfen
                            </button>
                        @elseif($lc === 'abgeschlossen')
                            <button
                                type="button"
                                wire:click="reopenProject"
                                class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-success)]/10"
                            >
                                @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                                Wieder öffnen
                            </button>
                        @elseif($lc === 'verworfen')
                            <button
                                type="button"
                                wire:click="reviveProject"
                                class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-success)]/10"
                            >
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                Zurückholen
                            </button>
                        @endif
                    </div>
                    <p class="text-[10px] text-[var(--nx-muted)] leading-tight">
                        Aktiv ↔ Ruhend läuft automatisch (45d Inaktivität). Abschließen / Verwerfen sind manuelle Entscheidungen.
                    </p>
                </section>

                {{-- Verknüpfte Entities --}}
                @if(!empty($entityLinks))
                    <section class="space-y-2">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Verknüpfte Entities</h4>
                        <ul class="space-y-1">
                            @foreach($entityLinks as $link)
                                <li class="flex items-center gap-2 px-2.5 py-1.5 rounded border border-[color:var(--nx-line)] bg-[var(--nx-bg)] text-[12px]">
                                    @svg('heroicon-o-rectangle-group', 'w-3.5 h-3.5 text-[var(--nx-muted)] flex-shrink-0')
                                    <span class="text-[var(--nx-text)] font-medium truncate">{{ $link['entity_name'] }}</span>
                                    @if($link['entity_type'])
                                        <span class="text-[10px] text-[var(--nx-muted)] flex-shrink-0">({{ $link['entity_type'] }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <p class="text-[10px] text-[var(--nx-muted)] m-0">Verknüpfungen werden über die Projekt-Ansicht verwaltet.</p>
                    </section>
                @endif

                {{-- Teilnehmer/Mitgliedschaft entfernt: Zugriff kommt aus dem Org-Graphen
                     (Ersteller ODER strukturell erreichbar), nicht mehr aus einer
                     Projekt-Mitgliederliste. --}}

                {{-- Alter Abschluss-Block ist in die "Lebenszyklus"-Sektion oben gewandert. --}}

                {{-- Danger zone --}}
                @can('delete', $project)
                    <section class="space-y-2 pt-3 border-t border-[color:var(--nx-line)]">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-danger)] m-0">Gefahrenzone</h4>
                        <x-nx-button variant="danger" size="sm" wire:click="deleteProject" wire:confirm="Wirklich löschen?">Projekt löschen</x-nx-button>
                    </section>
                @endcan
            </div>

            {{-- ═══════════════════════════════════════════════════════════ --}}
            {{-- TAB: ABRECHNUNG                                             --}}
            {{-- ═══════════════════════════════════════════════════════════ --}}
            <div x-show="activeTab === 'billing'" x-transition class="space-y-4">
                @can('update', $project)
                    <section class="space-y-3">
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Abrechnung</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <x-nx-input-select
                                name="project.billing_method"
                                label="Abrechnungsmethode"
                                :options="$billingMethodOptions"
                                wire:model.live="project.billing_method"
                                nullable="true"
                                nullLabel="– wählen –"
                            />
                            <x-nx-input-text
                                name="project.hourly_rate"
                                label="Stundensatz"
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live.debounce.500ms="project.hourly_rate"
                                placeholder="z. B. 120.00"
                            />
                            <x-nx-input-text
                                name="project.budget_amount"
                                label="Budget"
                                type="number"
                                step="0.01"
                                min="0"
                                wire:model.live.debounce.500ms="project.budget_amount"
                                placeholder="z. B. 10000.00"
                            />
                            <x-nx-input-text
                                name="project.currency"
                                label="Währung"
                                wire:model.live.debounce.500ms="project.currency"
                                placeholder="EUR"
                                maxlength="3"
                            />
                        </div>
                    </section>
                @else
                    <section class="space-y-1.5 text-[12px]">
                        @if($project->billing_method)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--nx-bg)]">
                                <dt class="text-[var(--nx-muted)]">Abrechnungsmethode</dt>
                                <dd class="text-[var(--nx-text)] font-medium m-0">{{ $project->billing_method?->value ?? $project->billing_method }}</dd>
                            </div>
                        @endif
                        @if($project->hourly_rate)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--nx-bg)]">
                                <dt class="text-[var(--nx-muted)]">Stundensatz</dt>
                                <dd class="text-[var(--nx-text)] font-medium tabular-nums m-0">{{ number_format($project->hourly_rate, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</dd>
                            </div>
                        @endif
                        @if($project->budget_amount)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-2.5 rounded bg-[var(--nx-bg)]">
                                <dt class="text-[var(--nx-muted)]">Budget</dt>
                                <dd class="text-[var(--nx-text)] font-medium tabular-nums m-0">{{ number_format($project->budget_amount, 2, ',', '.') }} {{ $project->currency ?? 'EUR' }}</dd>
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
                        <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Öffentlicher Link</h4>
                        <p class="text-[12px] text-[var(--nx-muted)] m-0">
                            Teile das Projekt-Board per Link. Jeder mit dem Link kann das Board read-only ansehen — ohne Login.
                        </p>

                        @if($isPublic && $publicUrl)
                            <div class="flex items-center gap-2 p-3 rounded-lg border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/5">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 text-[var(--nx-success)] flex-shrink-0')
                                <span class="text-[12px] font-medium text-[var(--nx-success)]">Öffentlicher Link ist aktiv</span>
                            </div>

                            <div x-data="{ copied: false }">
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-1.5">Link</label>
                                <div class="flex gap-1.5">
                                    <input
                                        type="text"
                                        value="{{ $publicUrl }}"
                                        readonly
                                        class="flex-1 px-2.5 py-1.5 text-[12px] bg-[var(--nx-bg)] border border-[color:var(--nx-line)] rounded-md text-[var(--nx-text)] select-all focus:outline-none focus:ring-2 focus:ring-[var(--nx-accent)]/20 tabular-nums"
                                    />
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText('{{ $publicUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="inline-flex items-center gap-1.5 px-3 text-[12px] font-medium rounded-md border transition-colors"
                                        :class="copied ? 'bg-[var(--nx-success)]/10 border-[var(--nx-success)]/30 text-[var(--nx-success)]' : 'bg-[color:var(--nx-surface)] border-[color:var(--nx-line)] text-[var(--nx-text)] hover:border-[var(--nx-accent)]/60'"
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
                                <x-nx-button variant="secondary" size="sm" wire:click="regeneratePublicLink">
                                    @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                    <span>Neuen Link generieren</span>
                                </x-nx-button>
                                <x-nx-button variant="danger" size="sm" wire:click="disablePublicLink">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                    <span>Deaktivieren</span>
                                </x-nx-button>
                            </div>
                            <p class="text-[10px] text-[var(--nx-muted)] m-0">Beim Generieren wird der alte Link ungültig.</p>
                        @else
                            <div class="flex items-center gap-2 p-3 rounded-lg border border-[color:var(--nx-line)] bg-[var(--nx-bg)]">
                                @svg('heroicon-o-lock-closed', 'w-4 h-4 text-[var(--nx-muted)] flex-shrink-0')
                                <span class="text-[12px] text-[var(--nx-muted)]">Kein öffentlicher Link aktiv</span>
                            </div>
                            <x-nx-button variant="primary" size="sm" wire:click="enablePublicLink">
                                @svg('heroicon-o-link', 'w-3.5 h-3.5')
                                <span>Öffentlichen Link erstellen</span>
                            </x-nx-button>
                        @endif
                    </section>
                </div>
            @endcan
        @endif

        <x-slot name="footer">
            @if($project)
                <div x-show="activeTab === 'general' || activeTab === 'billing'" class="flex justify-end gap-2">
                    @can('update', $project)
                        <x-nx-button variant="primary" size="sm" wire:click="save">
                            @svg('heroicon-o-check', 'w-3.5 h-3.5')
                            <span>Speichern</span>
                        </x-nx-button>
                    @endcan
                </div>
            @endif
        </x-slot>
    </x-nx-modal>
</div>
