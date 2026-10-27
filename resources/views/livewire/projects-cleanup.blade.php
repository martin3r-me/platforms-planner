@php
    $rows = $this->rows;
    $totalRows = count($rows);
    $selectedCount = count($selectedIds);
    // KPI-Aggregate
    $kpiByColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
    $kpiByForgotten = ['fresh' => 0, 'warm' => 0, 'cold' => 0, 'buried' => 0, 'unknown' => 0];
    $kpiHours = 0;
    foreach ($rows as $r) {
        $kpiByColor[$r['health_color']] = ($kpiByColor[$r['health_color']] ?? 0) + 1;
        $kpiByForgotten[$r['forgotten_bucket']] = ($kpiByForgotten[$r['forgotten_bucket']] ?? 0) + 1;
        $kpiHours += $r['tracked_minutes'] / 60;
    }
    $kpiCleanupCandidates = $kpiByForgotten['cold'] + $kpiByForgotten['buried'];
    $pflegeVariant = $kpiCleanupCandidates > 0 ? 'warning' : 'success';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Pflege" icon="heroicon-o-shield-check" />
    </x-slot>

    @php
        $tone = function ($color) {
            return match ($color) {
                'red'    => ['bg' => 'bg-[var(--nx-danger)]/10',    'fg' => 'text-[color:var(--nx-danger)]',    'ring' => 'ring-[var(--nx-danger)]/30',    'border' => 'border-l-[color:var(--nx-danger)]'],
                'yellow' => ['bg' => 'bg-[var(--nx-warning)]/10',   'fg' => 'text-[color:var(--nx-warning)]',   'ring' => 'ring-[var(--nx-warning)]/30',   'border' => 'border-l-[color:var(--nx-warning)]'],
                'green'  => ['bg' => 'bg-[var(--nx-success)]/10', 'fg' => 'text-[color:var(--nx-success)]', 'ring' => 'ring-[var(--nx-success)]/30', 'border' => 'border-l-[color:var(--nx-success)]'],
                default  => ['bg' => 'bg-[color:var(--nx-bg)]',    'fg' => 'text-[color:var(--nx-muted)]',    'ring' => 'ring-[color:var(--nx-line)]',    'border' => 'border-l-zinc-300'],
            };
        };
        $forgottenTone = function ($bucket) {
            return match ($bucket) {
                'fresh'   => ['bg' => 'bg-[var(--nx-success)]/10', 'fg' => 'text-[color:var(--nx-success)]', 'label' => 'frisch',    'icon' => 'heroicon-o-fire'],
                'warm'    => ['bg' => 'bg-[var(--nx-warning)]/10',  'fg' => 'text-[color:var(--nx-warning)]',  'label' => 'warm',      'icon' => 'heroicon-o-sun'],
                'cold'    => ['bg' => 'bg-[var(--nx-warning)]/10',  'fg' => 'text-[color:var(--nx-warning)]',  'label' => 'kalt',      'icon' => 'heroicon-o-cloud'],
                'buried'  => ['bg' => 'bg-[var(--nx-danger)]/10',    'fg' => 'text-[color:var(--nx-danger)]',    'label' => 'vergraben', 'icon' => 'heroicon-o-archive-box-x-mark'],
                default   => ['bg' => 'bg-[color:var(--nx-bg)]',    'fg' => 'text-[color:var(--nx-muted)]',    'label' => 'unbekannt', 'icon' => 'heroicon-o-question-mark-circle'],
            };
        };
        $suspectDefs = [
            'no_owner'  => 'kein Owner',
            'no_entity' => 'keine Entity',
            'no_tasks'  => 'keine Tasks',
            'forgotten' => 'vergessen (>30d)',
        ];
        $lifecycleDefs = [
            'all'           => ['label' => 'Alle',           'tone' => 'zinc',    'icon' => 'heroicon-o-squares-2x2'],
            'aktiv'         => ['label' => 'Aktiv',          'tone' => 'emerald', 'icon' => 'heroicon-o-bolt'],
            'ruhend'        => ['label' => 'Ruhend',         'tone' => 'amber',   'icon' => 'heroicon-o-moon'],
            'abgeschlossen' => ['label' => 'Abgeschlossen',  'tone' => 'blue',    'icon' => 'heroicon-o-check-circle'],
            'verworfen'     => ['label' => 'Verworfen',      'tone' => 'zinc',    'icon' => 'heroicon-o-archive-box-x-mark'],
        ];
    @endphp

    <x-slot name="actionbar">
        <x-planner::actionbar
            :filters-active="($colorFilter !== 'all' ? 1 : 0) + ($lifecycleFilter !== 'aktiv' ? 1 : 0) + ($ownerFilter ? 1 : 0) + count($suspectFlags) + ($sort !== 'name' ? 1 : 0)">
            <x-slot:meta>
                {{-- Aufräum-Signal als EIN Badge rechts (Projekt-Standard) --}}
                <x-nx-badge :variant="$pflegeVariant" title="{{ $totalRows }} Projekte im Scope · {{ $kpiCleanupCandidates }} Aufräum-Kandidaten (>30d ohne Aktivität)">
                    @svg('heroicon-o-archive-box-x-mark', 'w-3 h-3')
                    <span class="tabular-nums">{{ $kpiCleanupCandidates }}</span>
                    <span class="opacity-40" aria-hidden="true">/</span>
                    <span class="tabular-nums">{{ $totalRows }}</span>
                </x-nx-badge>
            </x-slot:meta>

            <x-slot:filters>
                <x-planner::filter-group label="Ampel">
                    @foreach(['all' => ['Alle', null], 'red' => ['Rot', 'bg-[color:var(--nx-danger)]'], 'yellow' => ['Gelb', 'bg-[color:var(--nx-warning)]'], 'gray' => ['Grau', 'bg-[color:var(--nx-muted)]'], 'green' => ['Grün', 'bg-[color:var(--nx-success)]']] as $key => [$label, $dot])
                        <x-planner::chip :active="$colorFilter === $key" :dot="$dot" wire:click="$set('colorFilter', '{{ $key }}')">{{ $label }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>

                <x-planner::filter-group label="Lebenszyklus">
                    @foreach($lifecycleDefs as $key => $lc)
                        <x-planner::chip :active="$lifecycleFilter === $key" :icon="$lc['icon']" wire:click="$set('lifecycleFilter', '{{ $key }}')">{{ $lc['label'] }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>
                <p class="-mt-2.5 text-[10px] text-[var(--nx-muted)] leading-tight">Aktiv ↔ Ruhend automatisch (45d). Abgeschlossen/Verworfen manuell.</p>

                <x-planner::filter-group label="Owner" :stack="true">
                    <div class="max-h-56 overflow-y-auto flex flex-col gap-0.5">
                        <x-planner::chip :row="true" :active="$ownerFilter === null" wire:click="$set('ownerFilter', null)">Alle</x-planner::chip>
                        @foreach($this->ownerOptions as $id => $name)
                            <x-planner::chip :row="true" :active="$ownerFilter == $id" wire:click="$set('ownerFilter', {{ (int) $id }})">{{ $name }}</x-planner::chip>
                        @endforeach
                    </div>
                </x-planner::filter-group>

                <x-planner::filter-group label="Verdachtsflags">
                    @foreach($suspectDefs as $flag => $label)
                        @php $on = in_array($flag, $suspectFlags, true); @endphp
                        <x-planner::chip :active="$on" wire:click="$set('suspectFlags', {{ json_encode($on ? array_values(array_diff($suspectFlags, [$flag])) : array_values(array_unique(array_merge($suspectFlags, [$flag])))) }})">{{ $label }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>

                <x-planner::filter-group label="Sortierung">
                    @foreach(['name' => 'A–Z', 'score_asc' => 'Score ↑ (schwach zuerst)', 'last_view_desc' => 'Zuletzt geöffnet', 'forgotten_desc' => 'Am längsten vergessen', 'tasks_desc' => 'Meiste Tasks'] as $key => $label)
                        <x-planner::chip :active="$sort === $key" wire:click="$set('sort', '{{ $key }}')">{{ $label }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>
            </x-slot:filters>

            <x-slot:more>
                <x-planner::menu-item :icon="$showTrashed ? 'heroicon-s-trash' : 'heroicon-o-trash'" wire:click="toggleTrashed"
                    title="Papierkorb — gelöschte Projekte ansehen & wiederherstellen">
                    {{ $showTrashed ? 'Papierkorb verlassen' : 'Papierkorb' }}
                </x-planner::menu-item>
                <x-planner::menu-item icon="heroicon-o-user" :href="route('planner.hygiene')">Meine Sicht (persönlich)</x-planner::menu-item>
            </x-slot:more>
        </x-planner::actionbar>
    </x-slot>



    {{-- ════════ CONTENT ════════ --}}

    <div class="flex-1 min-w-0 flex flex-col bg-[var(--nx-bg)] min-h-0">

        {{-- Kopf: Scope Meins ⟷ Team + Suche + Kurzbeschreibung --}}
        <div class="shrink-0 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-3 sm:px-6 pt-3 pb-2 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex shrink-0 rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden" role="group" aria-label="Sicht">
                    <a href="{{ route('planner.hygiene') }}" wire:navigate
                       title="Meine Sicht (persönlich)"
                       class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition-colors">
                        @svg('heroicon-o-user', 'w-3.5 h-3.5')
                        Meins
                    </a>
                    <span class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] border-l border-[color:var(--nx-line-strong)]" aria-current="page">
                        @svg('heroicon-o-user-group', 'w-3.5 h-3.5')
                        Team
                    </span>
                </div>
                <div class="relative flex-1 min-w-[10rem] max-w-xs">
                    @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2 text-[var(--nx-muted)] pointer-events-none')
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Titel suchen …"
                        class="w-full h-7 pl-7 pr-2 text-[12px] rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[var(--nx-text)] placeholder:text-[var(--nx-muted)] focus:border-[var(--nx-accent)] focus:ring-1 focus:ring-[var(--nx-accent)]/40 outline-none"
                    />
                </div>
                @if($showTrashed)
                    <button type="button" wire:click="toggleTrashed"
                            class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium rounded-md border bg-[var(--nx-danger)]/10 text-[color:var(--nx-danger)] border-[var(--nx-danger)]/30"
                            title="Papierkorb verlassen">
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        Papierkorb aktiv
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
            </div>
            <p class="text-[11px] text-[var(--nx-muted)] m-0">
                <strong class="text-[var(--nx-text)]">Team-Pflege:</strong> Bulk-Auswahl und Inline-Aktionen — Löschen, Passiv/Inaktiv, Erledigt, Entity-Change, ohne Detail-Klick.
            </p>
        </div>


        {{-- Hero KPI-Bar --}}
        <div class="border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-3 sm:px-6 py-3 sm:py-4 flex items-stretch gap-3 flex-shrink-0 overflow-x-auto">
            {{-- Total --}}
            <div class="flex flex-col justify-between rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4 py-2.5 min-w-[110px] shadow-[var(--nx-shadow-card)]">
                <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Projekte</div>
                <div class="text-2xl font-bold tabular-nums text-[var(--nx-text)] leading-tight">{{ $totalRows }}</div>
                <div class="text-[10px] text-[var(--nx-muted)]">im Scope</div>
            </div>

            {{-- Ampel Row --}}
            <div class="grid grid-cols-4 gap-2 flex-shrink-0">
                @foreach([
                    'red'    => ['label' => 'Rot',  'bg' => 'bg-[var(--nx-danger)]/10',    'fg' => 'text-[color:var(--nx-danger)]',    'dot' => 'bg-[color:var(--nx-danger)]'],
                    'yellow' => ['label' => 'Gelb', 'bg' => 'bg-[var(--nx-warning)]/10',   'fg' => 'text-[color:var(--nx-warning)]',   'dot' => 'bg-[color:var(--nx-warning)]'],
                    'green'  => ['label' => 'Grün', 'bg' => 'bg-[var(--nx-success)]/10', 'fg' => 'text-[color:var(--nx-success)]', 'dot' => 'bg-[color:var(--nx-success)]'],
                    'gray'   => ['label' => 'Grau', 'bg' => 'bg-[color:var(--nx-bg)]',    'fg' => 'text-[color:var(--nx-muted)]',    'dot' => 'bg-[color:var(--nx-muted)]'],
                ] as $key => $meta)
                    <button
                        wire:click="$set('colorFilter', '{{ $colorFilter === $key ? 'all' : $key }}')"
                        class="flex flex-col justify-between rounded-xl border border-[color:var(--nx-line)] {{ $colorFilter === $key ? $meta['bg'] . ' ring-2 ring-offset-1 ring-current ' . $meta['fg'] : 'bg-[color:var(--nx-surface)] hover:' . $meta['bg'] }} px-3 py-2.5 min-w-[76px] shadow-[var(--nx-shadow-card)] transition-all text-left"
                        title="Nach {{ $meta['label'] }} filtern"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">{{ $meta['label'] }}</span>
                        </div>
                        <div class="text-2xl font-bold tabular-nums {{ $meta['fg'] }} leading-tight">{{ $kpiByColor[$key] }}</div>
                        <div class="text-[10px] text-[var(--nx-muted)]">{{ $totalRows > 0 ? round($kpiByColor[$key] / $totalRows * 100) : 0 }}%</div>
                    </button>
                @endforeach
            </div>

            {{-- Vergessen --}}
            <button
                wire:click="$set('suspectFlags', {{ in_array('forgotten', $suspectFlags, true) ? json_encode(array_values(array_diff($suspectFlags, ['forgotten']))) : json_encode(array_values(array_unique(array_merge($suspectFlags, ['forgotten'])))) }})"
                class="flex flex-col justify-between rounded-xl border {{ in_array('forgotten', $suspectFlags, true) ? 'border-[var(--nx-warning)]/30 bg-[var(--nx-warning)]/10 ring-2 ring-offset-1 ring-[var(--nx-warning)]/30' : 'border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] hover:bg-[var(--nx-warning)]/10' }} px-4 py-2.5 min-w-[120px] shadow-[var(--nx-shadow-card)] transition-all text-left"
                title="Vergessene Projekte (Aktivität > 30 Tage her)"
            >
                <div class="flex items-center gap-1.5">
                    @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5 text-[color:var(--nx-warning)]')
                    <span class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Vergessen</span>
                </div>
                <div class="text-2xl font-bold tabular-nums text-[color:var(--nx-warning)] leading-tight">{{ $kpiCleanupCandidates }}</div>
                <div class="text-[10px] text-[var(--nx-muted)]">> 30d ohne Aktivität</div>
            </button>

            {{-- Tracked Time --}}
            <div class="flex flex-col justify-between rounded-xl border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4 py-2.5 min-w-[110px] shadow-[var(--nx-shadow-card)]">
                <div class="flex items-center gap-1.5">
                    @svg('heroicon-o-clock', 'w-3.5 h-3.5 text-[var(--nx-muted)]')
                    <span class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)]">Zeit</span>
                </div>
                <div class="text-2xl font-bold tabular-nums text-[var(--nx-text)] leading-tight">{{ number_format($kpiHours, 0, ',', '.') }}<span class="text-sm font-normal text-[var(--nx-muted)] ml-0.5">h</span></div>
                <div class="text-[10px] text-[var(--nx-muted)]">summiert</div>
            </div>

            {{-- Selected --}}
            @if($selectedCount > 0)
                <div class="flex flex-col justify-between rounded-xl border border-[var(--nx-accent)]/30 bg-[var(--nx-accent)]/10 px-4 py-2.5 min-w-[110px] shadow-[var(--nx-shadow-card)]">
                    <div class="flex items-center gap-1.5">
                        @svg('heroicon-o-check-badge', 'w-3.5 h-3.5 text-[color:var(--nx-accent)]')
                        <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-accent)]">Ausgewählt</span>
                    </div>
                    <div class="text-2xl font-bold tabular-nums text-[color:var(--nx-accent)] leading-tight">{{ $selectedCount }}</div>
                    <button wire:click="clearSelection" class="text-[10px] text-[color:var(--nx-accent)] hover:underline text-left">zurücksetzen</button>
                </div>
            @endif

            {{-- Session message on right --}}
            @if(session('cleanup_message'))
                <div class="ml-auto flex items-center gap-2 rounded-xl border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/10 px-3 py-2 text-[color:var(--nx-success)] text-[11px] shadow-[var(--nx-shadow-card)]">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    {{ session('cleanup_message') }}
                </div>
            @endif
        </div>

        {{-- Papierkorb-Hinweis --}}
        @if($showTrashed)
            <div class="border-b border-[var(--nx-danger)]/20 bg-[var(--nx-danger)]/5 px-3 sm:px-6 py-2 flex flex-wrap items-center gap-2 text-[11px] text-[color:var(--nx-danger)] flex-shrink-0">
                @svg('heroicon-o-trash', 'w-4 h-4 flex-shrink-0')
                <span><strong>Papierkorb</strong> — soft-gelöschte Projekte. „Wiederherstellen" holt Projekt + Aufgaben + Canvases zurück (Entity-Verknüpfung ggf. neu setzen).</span>
                <button wire:click="toggleTrashed" class="ml-auto underline hover:no-underline whitespace-nowrap">zurück zur Pflege</button>
            </div>
        @endif

        {{-- Bulk-Toolbar --}}
        @if($selectedCount > 0)
            <div class="border-b border-[color:var(--nx-line)] bg-[var(--nx-accent)]/5 px-3 sm:px-6 py-2 flex flex-wrap items-center gap-3 flex-shrink-0">
                <button wire:click="clearSelection" class="text-[11px] text-[var(--nx-muted)] hover:text-[var(--nx-text)] underline">Auswahl zurücksetzen</button>
                <div class="ml-auto flex items-center gap-2">
                    @if($showTrashed)
                    <button
                        wire:click="bulkRestore"
                        class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-success)]/10"
                        title="Ausgewählte Projekte wiederherstellen"
                    >
                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                        Wiederherstellen
                    </button>
                    @else
                    <button
                        wire:click="bulkComplete"
                        class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-info)]/30 bg-[var(--nx-info)]/10 text-[color:var(--nx-info)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-info)]/10"
                        title="Abschließen — Ziel erreicht, Read-only"
                    >
                        @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                        Abschließen
                    </button>
                    <button
                        wire:click="bulkDiscard"
                        class="inline-flex items-center gap-1 rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] text-[color:var(--nx-text)] px-2.5 py-1 text-[11px] font-medium hover:bg-[color:var(--nx-line)]"
                        title="Verwerfen — offene Tasks werden mit-verworfen"
                    >
                        @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5')
                        Verwerfen
                    </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Tabelle --}}
        <div class="flex-1 overflow-auto">
            <div class="px-3 sm:px-6 py-4 sm:py-6">
                <div class="min-w-[1320px] bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">

                    @php
                        $gridCols = 'grid-cols-[36px_60px_1fr_140px_180px_110px_140px_80px_160px_190px]';
                    @endphp

                    {{-- Header --}}
                    <div class="{{ $gridCols }} grid gap-2 items-center pl-4 pr-3 py-2 border-b-2 border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] text-[10px] uppercase tracking-wider text-[var(--nx-muted)] font-semibold sticky top-0 z-10 backdrop-blur">
                        <div>
                            <input
                                type="checkbox"
                                wire:click="selectAllVisible"
                                class="rounded border-[color:var(--nx-line)]"
                                @if($selectedCount > 0 && $selectedCount === $totalRows) checked @endif
                            />
                        </div>
                        <div class="text-center">Score</div>
                        <div>Projekt</div>
                        <div>Owner</div>
                        <div>Entity</div>
                        <div class="text-center" title="Canvas / Period / Minutes / Tasks">Layer</div>
                        <div class="text-center">Vergessen seit</div>
                        <div class="text-right">Zeit</div>
                        <div class="text-center">Tasks · offen/over/frog</div>
                        <div class="text-right">Aktionen</div>
                    </div>

                    {{-- Zeilen --}}
                    @forelse($rows as $row)
                        @php
                            $t = $tone($row['health_color']);
                            $ft = $forgottenTone($row['forgotten_bucket']);
                            $isSelected = in_array($row['id'], $selectedIds, true);
                        @endphp
                        <div class="{{ $gridCols }} grid gap-2 items-center pl-3 pr-3 py-2 border-b border-[color:var(--nx-line)] border-l-4 {{ $t['border'] }} {{ $isSelected ? 'bg-[var(--nx-accent)]/10' : 'hover:bg-[var(--nx-bg)]/60' }} transition-colors text-sm">
                            <div>
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelection({{ $row['id'] }})"
                                    @if($isSelected) checked @endif
                                    class="rounded border-[color:var(--nx-line)]"
                                />
                            </div>

                            {{-- Score als runde Badge --}}
                            <div class="flex justify-center">
                                @if($row['health_score'] !== null)
                                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full font-bold tabular-nums text-sm {{ $t['bg'] }} {{ $t['fg'] }} ring-2 ring-inset {{ $t['ring'] }}"
                                         title="Health-Score {{ $row['health_score'] }} / 100">
                                        {{ $row['health_score'] }}
                                    </div>
                                @else
                                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full text-[color:var(--nx-faint)] bg-[color:var(--nx-bg)] ring-2 ring-inset ring-[color:var(--nx-line)]"
                                         title="Kein Score — vermutlich fehlen Bausteine (siehe Layer)">
                                        <span class="text-lg">·</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Projekt-Titel --}}
                            <div class="min-w-0">
                                @if($showTrashed)
                                    <span class="font-semibold text-[var(--nx-text)] truncate block" title="{{ $row['name'] }} (gelöscht)">
                                        {{ $row['name'] }}
                                    </span>
                                @else
                                    <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank" class="font-semibold text-[var(--nx-text)] hover:text-[var(--nx-accent)] truncate block" title="{{ $row['name'] }}">
                                        {{ $row['name'] }}
                                    </a>
                                @endif
                                <div class="flex items-center gap-1 mt-0.5 text-[10px] text-[var(--nx-muted)]">
                                    @if($row['kind'])
                                        <span class="uppercase tracking-wider px-1 py-0.5 rounded bg-[var(--nx-bg)]">{{ $row['kind'] }}</span>
                                    @endif
                                    @php
                                        $lc = $row['lifecycle_state'];
                                        $lcChip = match($lc) {
                                            'ruhend'        => ['label' => 'ruhend',        'cls' => 'bg-[var(--nx-warning)]/10 text-[color:var(--nx-warning)] border border-[var(--nx-warning)]/30'],
                                            'abgeschlossen' => ['label' => 'abgeschlossen', 'cls' => 'bg-[var(--nx-info)]/10 text-[color:var(--nx-info)] border border-[var(--nx-info)]/30'],
                                            'verworfen'     => ['label' => 'verworfen',     'cls' => 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] border border-[color:var(--nx-line)]'],
                                            default => null,
                                        };
                                    @endphp
                                    @if($lcChip)
                                        <span class="uppercase tracking-wider px-1 py-0.5 rounded {{ $lcChip['cls'] }}">{{ $lcChip['label'] }}</span>
                                    @endif
                                    <span class="inline-flex items-center gap-0.5 text-[10px] text-[var(--nx-muted)]" title="Members am Projekt">
                                        @svg('heroicon-o-user-group', 'w-3 h-3')
                                        <span class="tabular-nums {{ $row['members_count'] === 0 ? 'text-[color:var(--nx-danger)] font-semibold' : '' }}">{{ $row['members_count'] }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="text-xs text-[var(--nx-text)] truncate" title="{{ $row['owner_name'] }}">
                                @if($row['owner_id'])
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-5 h-5 rounded-full bg-[var(--nx-accent)]/10 text-[color:var(--nx-accent)] inline-flex items-center justify-center text-[10px] font-semibold flex-shrink-0">
                                            {{ mb_strtoupper(mb_substr($row['owner_name'], 0, 1)) }}
                                        </span>
                                        <span class="truncate">{{ $row['owner_name'] }}</span>
                                    </span>
                                @else
                                    <span class="text-[color:var(--nx-danger)] inline-flex items-center gap-1">
                                        @svg('heroicon-o-user-minus', 'w-3.5 h-3.5')
                                        kein Owner
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0">
                                @if($row['entity_name'])
                                    <button
                                        type="button"
                                        wire:click="openEntityModal({{ $row['id'] }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-[var(--nx-accent)]/10 border border-[var(--nx-accent)]/30 text-[color:var(--nx-accent)] px-2 py-0.5 text-[11px] truncate max-w-full hover:bg-[var(--nx-accent)]/10"
                                        title="{{ $row['entity_name'] }} — klicken zum Ändern"
                                    >
                                        @svg('heroicon-o-tag', 'w-3 h-3 flex-shrink-0')
                                        <span class="truncate">{{ $row['entity_name'] }}</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="openEntityModal({{ $row['id'] }})"
                                        class="inline-flex items-center gap-1 rounded-md bg-[var(--nx-danger)]/10 border border-[var(--nx-danger)]/30 text-[color:var(--nx-danger)] px-2 py-0.5 text-[11px] hover:bg-[var(--nx-danger)]/10"
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
                                        class="inline-flex items-center justify-center w-5 h-5 rounded text-[10px] font-bold {{ $on ? 'bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] border border-[var(--nx-success)]/30' : 'bg-[color:var(--nx-line)] text-[color:var(--nx-faint)] border border-[color:var(--nx-line)]' }}"
                                        title="{{ $layerLabels[$key] }}: {{ $on ? 'vorhanden' : 'fehlt' }}"
                                    >{{ $letter }}</span>
                                @endforeach
                            </div>

                            {{-- Vergessen-Chip --}}
                            <div class="flex justify-center">
                                @if($row['forgotten_days'] !== null)
                                    <span class="inline-flex items-center gap-1 rounded-full {{ $ft['bg'] }} {{ $ft['fg'] }} px-2 py-0.5 text-[11px] font-medium tabular-nums"
                                          title="Letzte Aktivität: {{ $row['last_activity_at']?->format('d.m.Y') }} — {{ $ft['label'] }}">
                                        @svg($ft['icon'], 'w-3 h-3 flex-shrink-0')
                                        {{ $row['forgotten_days'] }} d
                                    </span>
                                @else
                                    <span class="text-xs text-[color:var(--nx-faint)]">–</span>
                                @endif
                            </div>

                            {{-- Zeit --}}
                            <div class="text-right text-xs tabular-nums" title="{{ $row['tracked_minutes'] }} min = {{ number_format($row['tracked_minutes'] / 60, 1, ',', '.') }} h">
                                @if($row['tracked_minutes'] > 0)
                                    <span class="font-medium text-[var(--nx-text)]">{{ number_format($row['tracked_minutes'] / 60, 1, ',', '.') }} h</span>
                                @else
                                    <span class="text-[color:var(--nx-faint)]">–</span>
                                @endif
                            </div>

                            {{-- Tasks --}}
                            <div class="text-center text-xs tabular-nums text-[var(--nx-muted)]">
                                <span class="text-[var(--nx-text)] font-medium">{{ $row['tasks_open'] }}</span>
                                <span class="text-[color:var(--nx-faint)]">/</span>
                                <span class="{{ $row['tasks_overdue'] > 0 ? 'text-[color:var(--nx-danger)] font-semibold' : '' }}">{{ $row['tasks_overdue'] }}</span>
                                <span class="text-[color:var(--nx-faint)]">/</span>
                                <span class="{{ $row['tasks_frog'] > 0 ? 'text-[color:var(--nx-warning)] font-semibold' : '' }}">{{ $row['tasks_frog'] }}</span>
                            </div>

                            {{-- Aktionen — zustandsabhängig --}}
                            <div class="flex items-center gap-0.5 justify-end">
                                @if($showTrashed)
                                <button
                                    type="button"
                                    wire:click="restore({{ $row['id'] }})"
                                    class="inline-flex items-center gap-1 rounded-md border border-[var(--nx-success)]/30 bg-[var(--nx-success)]/10 text-[color:var(--nx-success)] px-2.5 py-1 text-[11px] font-medium hover:bg-[var(--nx-success)]/10"
                                    title="Projekt + Aufgaben + Canvases wiederherstellen"
                                >
                                    @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5')
                                    Wiederherstellen
                                </button>
                                @else
                                <button
                                    type="button"
                                    wire:click="openEntityModal({{ $row['id'] }})"
                                    class="p-1.5 rounded hover:bg-[var(--nx-accent)]/10 text-[color:var(--nx-accent)]"
                                    title="Entity ändern"
                                >
                                    @svg('heroicon-o-tag', 'w-4 h-4')
                                </button>

                                @if(in_array($row['lifecycle_state'], ['aktiv', 'ruhend'], true))
                                    <button
                                        type="button"
                                        wire:click="complete({{ $row['id'] }})"
                                        class="p-1.5 rounded hover:bg-[var(--nx-info)]/10 text-[color:var(--nx-info)]"
                                        title="Abschließen (Ziel erreicht)"
                                    >
                                        @svg('heroicon-o-check-circle', 'w-4 h-4')
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="discard({{ $row['id'] }})"
                                        class="p-1.5 rounded hover:bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]"
                                        title="Verwerfen (kaskadiert offene Tasks)"
                                    >
                                        @svg('heroicon-o-archive-box-x-mark', 'w-4 h-4')
                                    </button>
                                @elseif($row['lifecycle_state'] === 'abgeschlossen')
                                    <button
                                        type="button"
                                        wire:click="reopen({{ $row['id'] }})"
                                        class="p-1.5 rounded hover:bg-[var(--nx-success)]/10 text-[color:var(--nx-success)]"
                                        title="Wieder öffnen"
                                    >
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                    </button>
                                @elseif($row['lifecycle_state'] === 'verworfen')
                                    <button
                                        type="button"
                                        wire:click="revive({{ $row['id'] }})"
                                        class="p-1.5 rounded hover:bg-[var(--nx-success)]/10 text-[color:var(--nx-success)]"
                                        title="Zurückholen"
                                    >
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                    </button>
                                @endif

                                <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank"
                                   class="p-1.5 rounded hover:bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]"
                                   title="Detail öffnen">
                                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                                </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center">
                            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--nx-bg)] mb-3">
                                @svg('heroicon-o-magnifying-glass', 'w-7 h-7 text-[var(--nx-muted)]')
                            </div>
                            @if($showTrashed)
                                <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 mb-1">Papierkorb ist leer</h3>
                                <p class="text-sm text-[var(--nx-muted)] m-0">Keine soft-gelöschten Projekte in diesem Team.</p>
                            @else
                                <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 mb-1">Keine Projekte passen zu deinen Filtern</h3>
                                <p class="text-sm text-[var(--nx-muted)] m-0">Lockere die Filter (Actionbar → Filter), um mehr zu sehen.</p>
                            @endif
                        </div>
                    @endforelse

                </div>
            </div>
        </div>
    </div>

    {{-- ════════ MODALS ════════ --}}

    {{-- Entity-Change-Modal --}}
    @if($editingProjectId)
        <div class="fixed inset-0 z-50 bg-[rgba(15,15,15,0.45)] flex items-center justify-center p-4">
            <div class="bg-[color:var(--nx-surface)] rounded-xl border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-pop)] w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-[var(--nx-text)] m-0 inline-flex items-center gap-2">
                    @svg('heroicon-o-tag', 'w-4 h-4 text-[color:var(--nx-accent)]')
                    Entity zuweisen
                </h3>
                <p class="text-xs text-[var(--nx-muted)] m-0">Ersetzt bestehende Entity-Links. Wähle das Ziel-Engagement.</p>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="entitySearch"
                    placeholder="Engagement suchen …"
                    class="w-full text-sm rounded-md border border-[color:var(--nx-line)] px-2.5 py-1.5"
                    autofocus
                />

                <div class="max-h-64 overflow-y-auto space-y-0.5 border border-[color:var(--nx-line)] rounded-md p-1 bg-[var(--nx-bg)]">
                    @foreach($this->engagementOptions as $id => $name)
                        <label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-[color:var(--nx-surface)] cursor-pointer">
                            <input type="radio" name="newEntity" wire:model.live="newEntityId" value="{{ $id }}" />
                            <span class="text-[13px] text-[var(--nx-text)]">{{ $name }}</span>
                        </label>
                    @endforeach
                    @if(count($this->engagementOptions) === 0)
                        <p class="text-xs text-[var(--nx-muted)] px-2 py-1">Keine Treffer.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-[color:var(--nx-line)]">
                    <button wire:click="closeEntityModal" class="text-xs text-[var(--nx-muted)] hover:text-[var(--nx-text)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="saveEntityChange"
                            @disabled(!$newEntityId)
                            class="rounded-md bg-[var(--nx-accent)] text-white px-3 py-1.5 text-xs font-medium disabled:opacity-50">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-ui-page>
