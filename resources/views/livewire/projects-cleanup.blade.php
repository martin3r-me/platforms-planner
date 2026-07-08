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
                'red'    => ['bg' => 'bg-rose-50',    'fg' => 'text-rose-700',    'ring' => 'ring-rose-200',    'border' => 'border-l-rose-500'],
                'yellow' => ['bg' => 'bg-amber-50',   'fg' => 'text-amber-700',   'ring' => 'ring-amber-200',   'border' => 'border-l-amber-500'],
                'green'  => ['bg' => 'bg-emerald-50', 'fg' => 'text-emerald-700', 'ring' => 'ring-emerald-200', 'border' => 'border-l-emerald-500'],
                default  => ['bg' => 'bg-zinc-50',    'fg' => 'text-zinc-600',    'ring' => 'ring-zinc-200',    'border' => 'border-l-zinc-300'],
            };
        };
        $forgottenTone = function ($bucket) {
            return match ($bucket) {
                'fresh'   => ['bg' => 'bg-emerald-50', 'fg' => 'text-emerald-700', 'label' => 'frisch',    'icon' => 'heroicon-o-fire'],
                'warm'    => ['bg' => 'bg-yellow-50',  'fg' => 'text-yellow-700',  'label' => 'warm',      'icon' => 'heroicon-o-sun'],
                'cold'    => ['bg' => 'bg-orange-50',  'fg' => 'text-orange-700',  'label' => 'kalt',      'icon' => 'heroicon-o-cloud'],
                'buried'  => ['bg' => 'bg-rose-50',    'fg' => 'text-rose-700',    'label' => 'vergraben', 'icon' => 'heroicon-o-archive-box-x-mark'],
                default   => ['bg' => 'bg-zinc-50',    'fg' => 'text-zinc-500',    'label' => 'unbekannt', 'icon' => 'heroicon-o-question-mark-circle'],
            };
        };
        $suspectDefs = [
            'no_owner'  => 'kein Owner',
            'no_entity' => 'keine Entity',
            'no_tasks'  => 'keine Tasks',
            'forgotten' => 'vergessen (>30d)',
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
                        <option value="forgotten_desc">Am längsten vergessen</option>
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
    @endphp

    <div class="flex-1 flex flex-col bg-[var(--ui-muted-5)] min-h-0">

        {{-- Hero KPI-Bar --}}
        <div class="border-b border-[var(--ui-border)]/40 bg-gradient-to-r from-white via-white to-[var(--ui-muted-5)] px-6 py-4 flex items-stretch gap-3 flex-shrink-0 overflow-x-auto">
            {{-- Total --}}
            <div class="flex flex-col justify-between rounded-xl border border-[var(--ui-border)]/50 bg-white px-4 py-2.5 min-w-[110px] shadow-sm">
                <div class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Projekte</div>
                <div class="text-2xl font-bold tabular-nums text-[var(--ui-secondary)] leading-tight">{{ $totalRows }}</div>
                <div class="text-[10px] text-[var(--ui-muted)]">im Scope</div>
            </div>

            {{-- Ampel Row --}}
            <div class="grid grid-cols-4 gap-2 flex-shrink-0">
                @foreach([
                    'red'    => ['label' => 'Rot',  'bg' => 'bg-rose-50',    'fg' => 'text-rose-700',    'dot' => 'bg-rose-500'],
                    'yellow' => ['label' => 'Gelb', 'bg' => 'bg-amber-50',   'fg' => 'text-amber-700',   'dot' => 'bg-amber-500'],
                    'green'  => ['label' => 'Grün', 'bg' => 'bg-emerald-50', 'fg' => 'text-emerald-700', 'dot' => 'bg-emerald-500'],
                    'gray'   => ['label' => 'Grau', 'bg' => 'bg-zinc-50',    'fg' => 'text-zinc-600',    'dot' => 'bg-zinc-400'],
                ] as $key => $meta)
                    <button
                        wire:click="$set('colorFilter', '{{ $colorFilter === $key ? 'all' : $key }}')"
                        class="flex flex-col justify-between rounded-xl border border-[var(--ui-border)]/50 {{ $colorFilter === $key ? $meta['bg'] . ' ring-2 ring-offset-1 ring-current ' . $meta['fg'] : 'bg-white hover:' . $meta['bg'] }} px-3 py-2.5 min-w-[76px] shadow-sm transition-all text-left"
                        title="Nach {{ $meta['label'] }} filtern"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">{{ $meta['label'] }}</span>
                        </div>
                        <div class="text-2xl font-bold tabular-nums {{ $meta['fg'] }} leading-tight">{{ $kpiByColor[$key] }}</div>
                        <div class="text-[10px] text-[var(--ui-muted)]">{{ $totalRows > 0 ? round($kpiByColor[$key] / $totalRows * 100) : 0 }}%</div>
                    </button>
                @endforeach
            </div>

            {{-- Vergessen --}}
            <button
                wire:click="$set('suspectFlags', {{ in_array('forgotten', $suspectFlags, true) ? json_encode(array_values(array_diff($suspectFlags, ['forgotten']))) : json_encode(array_values(array_unique(array_merge($suspectFlags, ['forgotten'])))) }})"
                class="flex flex-col justify-between rounded-xl border {{ in_array('forgotten', $suspectFlags, true) ? 'border-orange-300 bg-orange-50 ring-2 ring-offset-1 ring-orange-200' : 'border-[var(--ui-border)]/50 bg-white hover:bg-orange-50' }} px-4 py-2.5 min-w-[120px] shadow-sm transition-all text-left"
                title="Vergessene Projekte (Aktivität > 30 Tage her)"
            >
                <div class="flex items-center gap-1.5">
                    @svg('heroicon-o-archive-box-x-mark', 'w-3.5 h-3.5 text-orange-600')
                    <span class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Vergessen</span>
                </div>
                <div class="text-2xl font-bold tabular-nums text-orange-700 leading-tight">{{ $kpiCleanupCandidates }}</div>
                <div class="text-[10px] text-[var(--ui-muted)]">> 30d ohne Aktivität</div>
            </button>

            {{-- Tracked Time --}}
            <div class="flex flex-col justify-between rounded-xl border border-[var(--ui-border)]/50 bg-white px-4 py-2.5 min-w-[110px] shadow-sm">
                <div class="flex items-center gap-1.5">
                    @svg('heroicon-o-clock', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                    <span class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)]">Zeit</span>
                </div>
                <div class="text-2xl font-bold tabular-nums text-[var(--ui-secondary)] leading-tight">{{ number_format($kpiHours, 0, ',', '.') }}<span class="text-sm font-normal text-[var(--ui-muted)] ml-0.5">h</span></div>
                <div class="text-[10px] text-[var(--ui-muted)]">summiert</div>
            </div>

            {{-- Selected --}}
            @if($selectedCount > 0)
                <div class="flex flex-col justify-between rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 min-w-[110px] shadow-sm">
                    <div class="flex items-center gap-1.5">
                        @svg('heroicon-o-check-badge', 'w-3.5 h-3.5 text-indigo-600')
                        <span class="text-[10px] uppercase tracking-wider text-indigo-700">Ausgewählt</span>
                    </div>
                    <div class="text-2xl font-bold tabular-nums text-indigo-700 leading-tight">{{ $selectedCount }}</div>
                    <button wire:click="clearSelection" class="text-[10px] text-indigo-600 hover:underline text-left">zurücksetzen</button>
                </div>
            @endif

            {{-- Session message on right --}}
            @if(session('cleanup_message'))
                <div class="ml-auto flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 text-[11px] shadow-sm">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    {{ session('cleanup_message') }}
                </div>
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

                    @php
                        $gridCols = 'grid-cols-[36px_60px_1fr_140px_180px_110px_140px_80px_160px_190px]';
                    @endphp

                    {{-- Header --}}
                    <div class="{{ $gridCols }} grid gap-2 items-center pl-4 pr-3 py-2 border-b-2 border-[var(--ui-border)]/60 bg-gradient-to-b from-white to-[var(--ui-muted-5)] text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold sticky top-0 z-10 backdrop-blur">
                        <div>
                            <input
                                type="checkbox"
                                wire:click="selectAllVisible"
                                class="rounded border-[var(--ui-border)]"
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
                        <div class="{{ $gridCols }} grid gap-2 items-center pl-3 pr-3 py-2 border-b border-[var(--ui-border)]/25 border-l-4 {{ $t['border'] }} {{ $isSelected ? 'bg-indigo-50/50' : 'hover:bg-[var(--ui-muted-5)]/60' }} transition-colors text-sm">
                            <div>
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelection({{ $row['id'] }})"
                                    @if($isSelected) checked @endif
                                    class="rounded border-[var(--ui-border)]"
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
                                    <div class="inline-flex items-center justify-center w-11 h-11 rounded-full text-zinc-400 bg-zinc-50 ring-2 ring-inset ring-zinc-200"
                                         title="Kein Score — vermutlich fehlen Bausteine (siehe Layer)">
                                        <span class="text-lg">·</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Projekt-Titel --}}
                            <div class="min-w-0">
                                <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank" class="font-semibold text-[var(--ui-secondary)] hover:text-[var(--planner-status-active)] truncate block" title="{{ $row['name'] }}">
                                    {{ $row['name'] }}
                                </a>
                                <div class="flex items-center gap-1 mt-0.5 text-[10px] text-[var(--ui-muted)]">
                                    @if($row['kind'])
                                        <span class="uppercase tracking-wider px-1 py-0.5 rounded bg-[var(--ui-muted-5)]">{{ $row['kind'] }}</span>
                                    @endif
                                    @if($row['status'] === 'passiv')
                                        <span class="uppercase tracking-wider px-1 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200/60">passiv</span>
                                    @elseif($row['status'] === 'inaktiv')
                                        <span class="uppercase tracking-wider px-1 py-0.5 rounded bg-zinc-100 text-zinc-500 border border-zinc-200">inaktiv</span>
                                    @endif
                                    <span class="inline-flex items-center gap-0.5 text-[10px] text-[var(--ui-muted)]" title="Members am Projekt">
                                        @svg('heroicon-o-user-group', 'w-3 h-3')
                                        <span class="tabular-nums {{ $row['members_count'] === 0 ? 'text-rose-500 font-semibold' : '' }}">{{ $row['members_count'] }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="text-xs text-[var(--ui-secondary)] truncate" title="{{ $row['owner_name'] }}">
                                @if($row['owner_id'])
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 inline-flex items-center justify-center text-[10px] font-semibold flex-shrink-0">
                                            {{ mb_strtoupper(mb_substr($row['owner_name'], 0, 1)) }}
                                        </span>
                                        <span class="truncate">{{ $row['owner_name'] }}</span>
                                    </span>
                                @else
                                    <span class="text-rose-500 inline-flex items-center gap-1">
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
                                        class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-200 text-indigo-800 px-2 py-0.5 text-[11px] truncate max-w-full hover:bg-indigo-100"
                                        title="{{ $row['entity_name'] }} — klicken zum Ändern"
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

                            {{-- Vergessen-Chip --}}
                            <div class="flex justify-center">
                                @if($row['forgotten_days'] !== null)
                                    <span class="inline-flex items-center gap-1 rounded-full {{ $ft['bg'] }} {{ $ft['fg'] }} px-2 py-0.5 text-[11px] font-medium tabular-nums"
                                          title="Letzte Aktivität: {{ $row['last_activity_at']?->format('d.m.Y') }} — {{ $ft['label'] }}">
                                        @svg($ft['icon'], 'w-3 h-3 flex-shrink-0')
                                        {{ $row['forgotten_days'] }} d
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

                            {{-- Tasks --}}
                            <div class="text-center text-xs tabular-nums text-[var(--ui-muted)]">
                                <span class="text-[var(--ui-secondary)] font-medium">{{ $row['tasks_open'] }}</span>
                                <span class="text-[var(--ui-muted)]/50">/</span>
                                <span class="{{ $row['tasks_overdue'] > 0 ? 'text-rose-600 font-semibold' : '' }}">{{ $row['tasks_overdue'] }}</span>
                                <span class="text-[var(--ui-muted)]/50">/</span>
                                <span class="{{ $row['tasks_frog'] > 0 ? 'text-amber-600 font-semibold' : '' }}">{{ $row['tasks_frog'] }}</span>
                            </div>

                            {{-- Aktionen --}}
                            <div class="flex items-center gap-0.5 justify-end">
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
