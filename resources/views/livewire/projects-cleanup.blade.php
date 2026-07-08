<x-ui-page>
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Projects Cleanup" icon="heroicon-o-adjustments-horizontal" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Projects Cleanup'],
        ]" />
    </x-slot>

    @php
        $tone = function ($color) {
            return match ($color) {
                'red'    => ['bg' => 'bg-rose-50',    'fg' => 'text-rose-700'],
                'yellow' => ['bg' => 'bg-amber-50',   'fg' => 'text-amber-700'],
                'green'  => ['bg' => 'bg-emerald-50', 'fg' => 'text-emerald-700'],
                default  => ['bg' => 'bg-zinc-50',    'fg' => 'text-zinc-600'],
            };
        };
        $suspectDefs = [
            'no_owner' => 'kein Owner',
            'no_entity' => 'keine Entity',
            'no_tasks' => 'keine Tasks',
            'stale' => 'lange nicht geöffnet',
        ];
    @endphp

    {{-- ════════ LEFT SIDEBAR: Filter ════════ --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" icon="heroicon-o-funnel" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- ÜBER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Über</h3>
                    <p class="text-[11px] text-[var(--ui-secondary)] leading-relaxed m-0">
                        Dichte Sicht mit Bulk-Auswahl und Inline-Aktionen. Für die Strategie- und Aufräum-Rolle: <strong>Löschen</strong>, <strong>Passiv/Inaktiv</strong>, <strong>Erledigt</strong>, <strong>Entity-Change</strong> — alles ohne Detail-Klick.
                    </p>
                    <a href="{{ route('planner.hygiene') }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-[10px] text-[var(--ui-muted)] hover:text-[var(--planner-status-active)] underline">
                        @svg('heroicon-o-shield-check', 'w-3 h-3')
                        Zur Hygiene-Sicht
                    </a>
                </section>

                {{-- SUCHE --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Suche</h3>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Titel suchen …"
                        class="w-full text-[12px] rounded-md border border-[var(--ui-border)]/60 px-2 py-1.5 focus:border-[var(--planner-status-active)] focus:ring-1 focus:ring-[var(--planner-status-active)]/40 outline-none"
                    />
                </section>

                {{-- AMPEL --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Ampel</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['all' => 'Alle', 'red' => '🔴 Rot', 'yellow' => '🟡 Gelb', 'gray' => '⚪ Grau', 'green' => '🟢 Grün'] as $key => $label)
                            <button
                                wire:click="$set('colorFilter', '{{ $key }}')"
                                class="px-2 py-1 text-[11px] rounded-full font-medium transition-colors {{ $colorFilter === $key ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                {{-- STATUS --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Status</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['all' => 'Alle', 'aktiv' => 'Aktiv', 'passiv' => 'Passiv', 'inaktiv' => 'Inaktiv'] as $key => $label)
                            <button
                                wire:click="$set('statusFilter', '{{ $key }}')"
                                class="px-2 py-1 text-[11px] rounded-full font-medium transition-colors {{ $statusFilter === $key ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)]' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                    <label class="mt-2 flex items-center gap-1.5 text-[11px] text-[var(--ui-muted)] cursor-pointer select-none">
                        <input type="checkbox" wire:model.live="includeDone" class="rounded border-[var(--ui-border)]" />
                        Erledigte einblenden
                    </label>
                </section>

                {{-- OWNER --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Owner</h3>
                    <select wire:model.live="ownerFilter" class="w-full text-[12px] rounded-md border border-[var(--ui-border)]/60 px-2 py-1.5">
                        <option value="">Alle</option>
                        @foreach($this->ownerOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </section>

                {{-- VERDÄCHTIG --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Verdachtsflags</h3>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($suspectDefs as $flag => $label)
                            @php $on = in_array($flag, $suspectFlags, true); @endphp
                            <button
                                type="button"
                                wire:click="$set('suspectFlags', {{ json_encode($on ? array_values(array_diff($suspectFlags, [$flag])) : array_values(array_unique(array_merge($suspectFlags, [$flag])))) }})"
                                class="px-2 py-1 text-[11px] rounded-full font-medium transition-colors {{ $on ? 'bg-amber-100 text-amber-800 ring-1 ring-amber-200' : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-amber-50' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>
                </section>

                {{-- SORTIERUNG --}}
                <section class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Sortierung</h3>
                    <select wire:model.live="sort" class="w-full text-[12px] rounded-md border border-[var(--ui-border)]/60 px-2 py-1.5">
                        <option value="name">A–Z</option>
                        <option value="score_asc">Score ↑ (schwach zuerst)</option>
                        <option value="last_view_desc">Zuletzt geöffnet</option>
                        <option value="tasks_desc">Meiste Tasks</option>
                    </select>
                </section>

            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- ════════ CONTENT ════════ --}}
    @php
        $rows = $this->rows;
        $totalRows = count($rows);
        $selectedCount = count($selectedIds);
    @endphp

    <div class="flex-1 flex flex-col bg-[var(--ui-muted-5)] min-h-0">

        {{-- KPI-Bar --}}
        <div class="border-b border-[var(--ui-border)]/40 bg-white px-6 py-3 flex items-center gap-6 flex-shrink-0 text-[11px]">
            <span class="inline-flex items-center gap-1.5 text-[var(--ui-secondary)]">
                <span class="font-semibold tabular-nums">{{ $totalRows }}</span>
                <span class="text-[var(--ui-muted)]">Projekte</span>
            </span>
            @if($selectedCount > 0)
                <span class="inline-flex items-center gap-1.5 text-[var(--planner-status-active)]">
                    @svg('heroicon-o-check-badge', 'w-3.5 h-3.5')
                    <span class="font-semibold tabular-nums">{{ $selectedCount }}</span>
                    <span class="text-[var(--ui-muted)]">ausgewählt</span>
                </span>
            @endif
            @if(count($suspectFlags) > 0)
                <span class="inline-flex items-center gap-1.5 text-amber-700">
                    @svg('heroicon-o-adjustments-horizontal', 'w-3.5 h-3.5')
                    <span class="tabular-nums">{{ count($suspectFlags) }}</span>
                    <span class="text-[var(--ui-muted)]">Verdachtsflag{{ count($suspectFlags) > 1 ? 's' : '' }} aktiv</span>
                </span>
            @endif
            @if(session('cleanup_message'))
                <span class="ml-auto inline-flex items-center gap-1.5 text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2.5 py-0.5">
                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                    {{ session('cleanup_message') }}
                </span>
            @endif
        </div>

        {{-- Bulk-Toolbar --}}
        @if($selectedCount > 0)
            <div class="border-b border-[var(--ui-border)]/40 bg-[var(--planner-status-active)]/5 px-6 py-2 flex items-center gap-3 flex-shrink-0">
                <button wire:click="clearSelection" class="text-[11px] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] underline">Auswahl zurücksetzen</button>
                <div class="ml-auto flex items-center gap-2">
                    <button
                        wire:click="bulkMarkDone"
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-300 bg-emerald-50 text-emerald-800 px-2.5 py-1 text-[11px] font-medium hover:bg-emerald-100"
                    >
                        @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                        Erledigt
                    </button>
                    <button
                        wire:click="bulkSetPassiv"
                        class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 text-amber-800 px-2.5 py-1 text-[11px] font-medium hover:bg-amber-100"
                    >
                        @svg('heroicon-o-pause-circle', 'w-3.5 h-3.5')
                        Passiv
                    </button>
                    <button
                        wire:click="bulkSetInaktiv"
                        class="inline-flex items-center gap-1 rounded-md border border-zinc-300 bg-zinc-50 text-zinc-700 px-2.5 py-1 text-[11px] font-medium hover:bg-zinc-100"
                    >
                        @svg('heroicon-o-archive-box', 'w-3.5 h-3.5')
                        Inaktiv
                    </button>
                    <button
                        wire:click="askBulkDelete"
                        class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-rose-50 text-rose-800 px-2.5 py-1 text-[11px] font-medium hover:bg-rose-100"
                    >
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        Löschen
                    </button>
                </div>
            </div>
        @endif

        {{-- Tabelle --}}
        <div class="flex-1 overflow-y-auto">
            <div class="p-6">
                <div class="bg-white rounded-xl border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">

                    {{-- Header --}}
                    <div class="grid grid-cols-[36px_1fr_140px_60px_180px_110px_60px_80px_120px_160px_180px] gap-2 items-center px-3 py-2 border-b border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">
                        <div>
                            <input
                                type="checkbox"
                                wire:click="selectAllVisible"
                                class="rounded border-[var(--ui-border)]"
                                @if($selectedCount > 0 && $selectedCount === $totalRows) checked @endif
                            />
                        </div>
                        <div>Titel</div>
                        <div>Owner</div>
                        <div class="text-center">Members</div>
                        <div>Entity</div>
                        <div class="text-center" title="Canvas / Period / Minutes / Tasks — Bausteine für Health-Score">Layer</div>
                        <div class="text-center">Score</div>
                        <div class="text-right">Zeit</div>
                        <div>Zuletzt</div>
                        <div class="text-center">Tasks (of / over / frog)</div>
                        <div class="text-right">Aktionen</div>
                    </div>

                    {{-- Zeilen --}}
                    @forelse($rows as $row)
                        @php $t = $tone($row['health_color']); @endphp
                        <div class="grid grid-cols-[36px_1fr_140px_60px_180px_110px_60px_80px_120px_160px_180px] gap-2 items-center px-3 py-2 border-b border-[var(--ui-border)]/30 hover:bg-[var(--ui-muted-5)] text-sm">
                            <div>
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelection({{ $row['id'] }})"
                                    @if(in_array($row['id'], $selectedIds, true)) checked @endif
                                    class="rounded border-[var(--ui-border)]"
                                />
                            </div>

                            <div class="min-w-0">
                                <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank" class="font-medium text-[var(--ui-secondary)] hover:text-[var(--planner-status-active)] truncate block" title="{{ $row['name'] }}">
                                    {{ $row['name'] }}
                                </a>
                                <div class="flex items-center gap-1 mt-0.5">
                                    @if($row['kind'])
                                        <span class="text-[9px] uppercase tracking-wider px-1 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $row['kind'] }}</span>
                                    @endif
                                    @if($row['status'] !== 'aktiv')
                                        <span class="text-[9px] uppercase tracking-wider px-1 py-0.5 rounded bg-amber-50 text-amber-700">{{ $row['status'] }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-xs text-[var(--ui-secondary)] truncate" title="{{ $row['owner_name'] }}">
                                {{ $row['owner_name'] }}
                            </div>

                            <div class="text-center text-xs tabular-nums {{ $row['members_count'] === 0 ? 'text-rose-500' : 'text-[var(--ui-muted)]' }}">
                                {{ $row['members_count'] }}
                            </div>

                            <div class="min-w-0">
                                @if($row['entity_name'])
                                    <button
                                        type="button"
                                        wire:click="openEntityModal({{ $row['id'] }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-800 px-2 py-0.5 text-[11px] truncate max-w-full hover:bg-indigo-100"
                                        title="{{ $row['entity_name'] }}"
                                    >
                                        @svg('heroicon-o-tag', 'w-3 h-3 flex-shrink-0')
                                        <span class="truncate">{{ $row['entity_name'] }}</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="openEntityModal({{ $row['id'] }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-200 text-rose-700 px-2 py-0.5 text-[11px] hover:bg-rose-100"
                                    >
                                        @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                        keine Entity
                                    </button>
                                @endif
                            </div>

                            {{-- Layer-Chips --}}
                            <div class="flex items-center gap-0.5 justify-center">
                                @php
                                    $layerDefs = ['canvas' => 'C', 'period' => 'P', 'minutes' => 'M', 'tasks' => 'T'];
                                    $layerLabels = ['canvas' => 'Canvas', 'period' => 'Planned Period', 'minutes' => 'Planned Minutes', 'tasks' => 'Tasks'];
                                @endphp
                                @foreach($layerDefs as $key => $letter)
                                    @php $on = (bool) ($row['layers'][$key] ?? false); @endphp
                                    <span
                                        class="inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold {{ $on ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-zinc-100 text-zinc-400 border border-zinc-200' }}"
                                        title="{{ $layerLabels[$key] }}: {{ $on ? 'vorhanden' : 'fehlt' }}"
                                    >{{ $letter }}</span>
                                @endforeach
                            </div>

                            {{-- Score --}}
                            <div class="text-center">
                                @if($row['health_score'] !== null)
                                    <span class="inline-flex items-center justify-center min-w-[36px] px-1.5 py-0.5 rounded font-semibold tabular-nums {{ $t['bg'] }} {{ $t['fg'] }} text-xs">
                                        {{ $row['health_score'] }}
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">–</span>
                                @endif
                            </div>

                            {{-- Zeit --}}
                            <div class="text-right text-xs tabular-nums" title="{{ $row['tracked_minutes'] }} min = {{ number_format($row['tracked_minutes'] / 60, 1, ',', '.') }} h">
                                @if($row['tracked_minutes'] > 0)
                                    <span class="font-medium text-[var(--ui-secondary)]">{{ number_format($row['tracked_minutes'] / 60, 1, ',', '.') }} h</span>
                                @else
                                    <span class="text-zinc-400">–</span>
                                @endif
                            </div>

                            {{-- Zuletzt --}}
                            <div class="text-xs text-[var(--ui-muted)]" title="{{ $row['last_viewed_at']?->format('d.m.Y H:i') ?? '' }}">
                                {{ $row['last_viewed_at']?->diffForHumans(short: true) ?? '–' }}
                            </div>

                            {{-- Tasks --}}
                            <div class="text-center text-xs tabular-nums text-[var(--ui-muted)]">
                                <span class="text-[var(--ui-secondary)] font-medium">{{ $row['tasks_open'] }}</span>
                                <span class="text-[var(--ui-muted)]/50">/</span>
                                <span class="{{ $row['tasks_overdue'] > 0 ? 'text-rose-600 font-semibold' : '' }}">{{ $row['tasks_overdue'] }}</span>
                                <span class="text-[var(--ui-muted)]/50">/</span>
                                <span class="{{ $row['tasks_frog'] > 0 ? 'text-amber-600 font-semibold' : '' }}">{{ $row['tasks_frog'] }}</span>
                            </div>

                            {{-- Aktionen --}}
                            <div class="flex items-center gap-1 justify-end">
                                <button
                                    type="button"
                                    wire:click="openEntityModal({{ $row['id'] }})"
                                    class="p-1.5 rounded hover:bg-indigo-50 text-indigo-600"
                                    title="Entity ändern"
                                >
                                    @svg('heroicon-o-tag', 'w-4 h-4')
                                </button>

                                @if($row['status'] === 'aktiv')
                                    <button
                                        type="button"
                                        wire:click="setStatus({{ $row['id'] }}, 'passiv')"
                                        class="p-1.5 rounded hover:bg-amber-50 text-amber-600"
                                        title="Auf Passiv setzen"
                                    >
                                        @svg('heroicon-o-pause-circle', 'w-4 h-4')
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="setStatus({{ $row['id'] }}, 'aktiv')"
                                        class="p-1.5 rounded hover:bg-emerald-50 text-emerald-600"
                                        title="Aktivieren"
                                    >
                                        @svg('heroicon-o-play-circle', 'w-4 h-4')
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="markDone({{ $row['id'] }})"
                                    class="p-1.5 rounded hover:bg-emerald-50 text-emerald-700"
                                    title="Als erledigt markieren"
                                >
                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                </button>

                                <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank"
                                   class="p-1.5 rounded hover:bg-zinc-100 text-zinc-500"
                                   title="Detail öffnen">
                                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                                </a>
                                <button
                                    type="button"
                                    wire:click="askDeleteSingle({{ $row['id'] }})"
                                    class="p-1.5 rounded hover:bg-rose-50 text-rose-600"
                                    title="Projekt komplett löschen (inkl. Aufgaben, Canvas, Entity-Links, Zeit-Einträge)"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--ui-muted-5)] mb-3">
                                @svg('heroicon-o-magnifying-glass', 'w-7 h-7 text-[var(--ui-muted)]')
                            </div>
                            <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Keine Projekte passen zu deinen Filtern</h3>
                            <p class="text-sm text-[var(--ui-muted)] m-0">Lockere die Filter links, um mehr zu sehen.</p>
                        </div>
                    @endforelse

                </div>
            </div>
        </div>
    </div>

    {{-- ════════ MODALS ════════ --}}

    {{-- Entity-Change-Modal --}}
    @if($editingProjectId)
        <div class="fixed inset-0 z-50 bg-zinc-900/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-[var(--ui-border)]/60 shadow-lg w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-tag', 'w-4 h-4 text-indigo-600')
                    Entity zuweisen
                </h3>
                <p class="text-xs text-[var(--ui-muted)] m-0">Ersetzt bestehende Entity-Links. Wähle das Ziel-Engagement.</p>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="entitySearch"
                    placeholder="Engagement suchen …"
                    class="w-full text-sm rounded-md border border-[var(--ui-border)]/60 px-2.5 py-1.5"
                    autofocus
                />

                <div class="max-h-64 overflow-y-auto space-y-0.5 border border-[var(--ui-border)]/40 rounded-md p-1 bg-[var(--ui-muted-5)]">
                    @foreach($this->engagementOptions as $id => $name)
                        <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-white cursor-pointer">
                            <input type="radio" name="newEntity" wire:model.live="newEntityId" value="{{ $id }}" />
                            <span class="text-[13px] text-[var(--ui-secondary)]">{{ $name }}</span>
                        </label>
                    @endforeach
                    @if(count($this->engagementOptions) === 0)
                        <p class="text-xs text-[var(--ui-muted)] px-2 py-1">Keine Treffer.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]/40">
                    <button wire:click="closeEntityModal" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="saveEntityChange"
                            @disabled(!$newEntityId)
                            class="rounded-md bg-[var(--planner-status-active)] text-white px-3 py-1.5 text-xs font-medium disabled:opacity-50">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Single-Delete-Confirm-Modal --}}
    @if($deletingProjectId)
        <div class="fixed inset-0 z-50 bg-zinc-900/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-rose-200 shadow-lg w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-rose-700 m-0 flex items-center gap-2">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    Projekt löschen?
                </h3>
                <p class="text-sm text-[var(--ui-secondary)] m-0">
                    <span class="font-semibold">{{ $deletingProjectName }}</span>
                </p>
                <p class="text-xs text-[var(--ui-muted)] m-0">
                    Entfernt komplett: Entity-/Dimension-Links, Planner-Canvas, Slots, Aufgaben und alle darauf gebuchten Zeit-Einträge. Das Projekt selbst wird soft-gelöscht.
                </p>
                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]/40">
                    <button wire:click="cancelDeleteSingle" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="confirmDeleteSingle"
                            class="rounded-md bg-rose-600 text-white px-3 py-1.5 text-xs font-medium hover:bg-rose-700">
                        Ja, löschen
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Bulk-Delete-Confirm-Modal --}}
    @if($confirmingBulkDelete)
        <div class="fixed inset-0 z-50 bg-zinc-900/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-rose-200 shadow-lg w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-rose-700 m-0 flex items-center gap-2">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    {{ count($selectedIds) }} Projekte komplett löschen?
                </h3>
                <p class="text-xs text-[var(--ui-muted)] m-0">
                    Entfernt bei jedem Projekt: Entity-/Dimension-Links, Planner-Canvas, Slots, Aufgaben und alle darauf gebuchten Zeit-Einträge. Das Projekt selbst wird soft-gelöscht (in DB wiederherstellbar).
                </p>
                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]/40">
                    <button wire:click="cancelBulkDelete" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="confirmBulkDelete"
                            class="rounded-md bg-rose-600 text-white px-3 py-1.5 text-xs font-medium hover:bg-rose-700">
                        Ja, löschen
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-ui-page>
