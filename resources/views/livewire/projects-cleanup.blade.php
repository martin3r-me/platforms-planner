<div>
@php
    $tone = function ($color) {
        return match ($color) {
            'red'    => ['bg' => 'bg-rose-50',    'fg' => 'text-rose-700',    'ring' => 'ring-rose-200'],
            'yellow' => ['bg' => 'bg-amber-50',   'fg' => 'text-amber-700',   'ring' => 'ring-amber-200'],
            'green'  => ['bg' => 'bg-emerald-50', 'fg' => 'text-emerald-700', 'ring' => 'ring-emerald-200'],
            default  => ['bg' => 'bg-zinc-50',    'fg' => 'text-zinc-600',    'ring' => 'ring-zinc-200'],
        };
    };
    $suspectDefs = [
        'no_owner' => 'kein Owner',
        'no_entity' => 'keine Entity',
        'no_tasks' => 'keine Tasks',
        'stale' => 'länger nicht geöffnet',
    ];
@endphp

<div class="max-w-[1400px] mx-auto p-4 space-y-4">
    {{-- Header --}}
    <div class="flex items-baseline justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Projects Cleanup</h1>
            <p class="text-sm text-[var(--ui-muted)] mt-1 mb-0">Dichte Sicht mit Filter, Bulk-Auswahl und Inline-Aktionen. Für Strategie & Aufräumen.</p>
        </div>
        <a href="{{ route('planner.health-index') }}" wire:navigate class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] underline">→ Health-Index</a>
    </div>

    @if(session('cleanup_message'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">
            {{ session('cleanup_message') }}
        </div>
    @endif

    {{-- Filter-Bar --}}
    <div class="rounded-xl border border-[var(--ui-border)] bg-white p-3 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px]">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Suche im Titel …"
                class="w-full text-sm rounded-lg border border-[var(--ui-border)] px-3 py-1.5 focus:border-[var(--planner-status-active)] focus:ring-1 focus:ring-[var(--planner-status-active)]/40 outline-none"
            />
        </div>

        {{-- Farbe --}}
        <div class="inline-flex rounded-lg overflow-hidden border border-[var(--ui-border)]">
            @foreach(['all' => 'Alle', 'red' => '🔴', 'yellow' => '🟡', 'gray' => '⚪', 'green' => '🟢'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('colorFilter', '{{ $key }}')"
                    class="px-2.5 py-1 text-xs {{ $colorFilter === $key ? 'bg-[var(--planner-status-active)]/15 text-[var(--planner-status-active)] font-semibold' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- Status --}}
        <select wire:model.live="statusFilter" class="text-xs rounded-lg border border-[var(--ui-border)] px-2 py-1">
            <option value="all">Status: Alle</option>
            <option value="aktiv">Aktiv</option>
            <option value="passiv">Passiv</option>
            <option value="inaktiv">Inaktiv</option>
        </select>

        {{-- Owner --}}
        <select wire:model.live="ownerFilter" class="text-xs rounded-lg border border-[var(--ui-border)] px-2 py-1 max-w-[180px]">
            <option value="">Owner: Alle</option>
            @foreach($this->ownerOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>

        {{-- Suspect-Flags --}}
        <div class="flex items-center gap-1">
            @foreach($suspectDefs as $flag => $label)
                @php $on = in_array($flag, $suspectFlags, true); @endphp
                <button
                    type="button"
                    wire:click="$set('suspectFlags', {{ json_encode($on ? array_values(array_diff($suspectFlags, [$flag])) : array_values(array_unique(array_merge($suspectFlags, [$flag])))) }})"
                    class="px-2 py-1 text-[11px] rounded-full border {{ $on ? 'bg-amber-100 border-amber-300 text-amber-800 font-semibold' : 'bg-white border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-amber-300' }}"
                    title="Verdachtsflag: {{ $label }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- Sort --}}
        <select wire:model.live="sort" class="text-xs rounded-lg border border-[var(--ui-border)] px-2 py-1">
            <option value="name">A–Z</option>
            <option value="score_asc">Score ↑ (schwach zuerst)</option>
            <option value="last_view_desc">Zuletzt geöffnet</option>
            <option value="tasks_desc">Meiste Tasks</option>
        </select>

        {{-- Done-Toggle --}}
        <label class="text-[11px] text-[var(--ui-muted)] inline-flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" wire:model.live="includeDone" class="rounded border-[var(--ui-border)]" />
            erledigte einblenden
        </label>
    </div>

    {{-- Bulk-Toolbar --}}
    @if(count($selectedIds) > 0)
        <div class="sticky top-0 z-10 rounded-xl border border-[var(--planner-status-active)]/40 bg-white shadow-md p-3 flex items-center gap-3">
            <span class="text-sm font-semibold text-[var(--ui-secondary)]">
                {{ count($selectedIds) }} ausgewählt
            </span>
            <button wire:click="clearSelection" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] underline">zurücksetzen</button>
            <div class="ml-auto flex items-center gap-2">
                <button
                    wire:click="bulkSetPassiv"
                    class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 px-3 py-1.5 text-xs font-medium hover:bg-amber-100"
                >
                    @svg('heroicon-o-pause', 'w-4 h-4')
                    Auf Passiv setzen
                </button>
                <button
                    wire:click="askBulkDelete"
                    class="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 text-rose-800 px-3 py-1.5 text-xs font-medium hover:bg-rose-100"
                >
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    Löschen
                </button>
            </div>
        </div>
    @endif

    {{-- Tabelle --}}
    <div class="rounded-xl border border-[var(--ui-border)] bg-white overflow-hidden">
        <div class="grid grid-cols-[36px_1fr_140px_60px_180px_60px_120px_160px_100px] gap-2 items-center px-3 py-2 border-b border-[var(--ui-border)] bg-[var(--ui-muted-5)] text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">
            <div>
                <input
                    type="checkbox"
                    wire:click="selectAllVisible"
                    class="rounded border-[var(--ui-border)]"
                    @if(count($selectedIds) > 0 && count($selectedIds) === count($this->rows)) checked @endif
                />
            </div>
            <div>Titel</div>
            <div>Owner</div>
            <div class="text-center">Members</div>
            <div>Entity</div>
            <div class="text-center">Score</div>
            <div>Zuletzt</div>
            <div class="text-center">Tasks (of / over / frog)</div>
            <div class="text-right">Aktionen</div>
        </div>

        @forelse($this->rows as $row)
            @php $t = $tone($row['health_color']); @endphp
            <div class="grid grid-cols-[36px_1fr_140px_60px_180px_60px_120px_160px_100px] gap-2 items-center px-3 py-2 border-b border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)] text-sm">
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

                <div class="text-center">
                    @if($row['health_score'] !== null)
                        <span class="inline-flex items-center justify-center min-w-[36px] px-1.5 py-0.5 rounded font-semibold tabular-nums {{ $t['bg'] }} {{ $t['fg'] }} text-xs">
                            {{ $row['health_score'] }}
                        </span>
                    @else
                        <span class="text-xs text-zinc-400">–</span>
                    @endif
                </div>

                <div class="text-xs text-[var(--ui-muted)]" title="{{ $row['last_viewed_at']?->format('d.m.Y H:i') ?? '' }}">
                    {{ $row['last_viewed_at']?->diffForHumans(short: true) ?? '–' }}
                </div>

                <div class="text-center text-xs tabular-nums text-[var(--ui-muted)]">
                    <span class="text-[var(--ui-secondary)] font-medium">{{ $row['tasks_open'] }}</span>
                    <span class="text-[var(--ui-muted)]/50">/</span>
                    <span class="{{ $row['tasks_overdue'] > 0 ? 'text-rose-600 font-semibold' : '' }}">{{ $row['tasks_overdue'] }}</span>
                    <span class="text-[var(--ui-muted)]/50">/</span>
                    <span class="{{ $row['tasks_frog'] > 0 ? 'text-amber-600 font-semibold' : '' }}">{{ $row['tasks_frog'] }}</span>
                </div>

                <div class="flex items-center gap-1 justify-end">
                    <button
                        type="button"
                        wire:click="openEntityModal({{ $row['id'] }})"
                        class="p-1.5 rounded hover:bg-indigo-50 text-indigo-600"
                        title="Entity ändern"
                    >
                        @svg('heroicon-o-tag', 'w-4 h-4')
                    </button>
                    <a href="{{ route('planner.projects.show', $row['id']) }}" target="_blank"
                       class="p-1.5 rounded hover:bg-zinc-100 text-zinc-500"
                       title="Detail öffnen">
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                    </a>
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-sm text-[var(--ui-muted)]">
                Keine Projekte passen zu deinen Filtern.
            </div>
        @endforelse
    </div>

    {{-- Entity-Change-Modal --}}
    @if($editingProjectId)
        <div class="fixed inset-0 z-50 bg-zinc-900/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-[var(--ui-border)] w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Entity zuweisen</h3>
                <p class="text-xs text-[var(--ui-muted)] m-0">Ersetzt bestehende Entity-Links. Wähle das Ziel-Engagement.</p>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="entitySearch"
                    placeholder="Engagement suchen …"
                    class="w-full text-sm rounded-lg border border-[var(--ui-border)] px-3 py-1.5"
                    autofocus
                />

                <div class="max-h-64 overflow-y-auto space-y-1">
                    @foreach($this->engagementOptions as $id => $name)
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-[var(--ui-muted-5)] cursor-pointer">
                            <input type="radio" name="newEntity" wire:model.live="newEntityId" value="{{ $id }}" />
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $name }}</span>
                        </label>
                    @endforeach
                    @if(count($this->engagementOptions) === 0)
                        <p class="text-xs text-[var(--ui-muted)] px-2 py-1">Keine Treffer.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]">
                    <button wire:click="closeEntityModal" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="saveEntityChange"
                            @disabled(!$newEntityId)
                            class="rounded-lg bg-[var(--planner-status-active)] text-white px-3 py-1.5 text-xs font-medium disabled:opacity-50">
                        Speichern
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Bulk-Delete-Confirm-Modal --}}
    @if($confirmingBulkDelete)
        <div class="fixed inset-0 z-50 bg-zinc-900/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl border border-rose-200 w-full max-w-md p-4 space-y-3">
                <h3 class="text-sm font-semibold text-rose-700 m-0 flex items-center gap-2">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    {{ count($selectedIds) }} Projekte löschen?
                </h3>
                <p class="text-xs text-[var(--ui-muted)] m-0">
                    Soft-Delete — Projekte werden entfernt aus Listen, sind aber in der DB wiederherstellbar. Ihre Tasks und Slots werden mit deaktiviert.
                </p>
                <div class="flex justify-end gap-2 pt-2 border-t border-[var(--ui-border)]">
                    <button wire:click="cancelBulkDelete" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] px-3 py-1.5">Abbrechen</button>
                    <button wire:click="confirmBulkDelete"
                            class="rounded-lg bg-rose-600 text-white px-3 py-1.5 text-xs font-medium hover:bg-rose-700">
                        Ja, löschen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
</div>
