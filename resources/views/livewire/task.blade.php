@php
    use Platform\Planner\Enums\TaskLifecycleState;
    $isDone      = $task->lifecycle_state === TaskLifecycleState::COMPLETED;
    $isDiscarded = $task->lifecycle_state === TaskLifecycleState::DISCARDED;
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$isDone && !$isDiscarded;
    $isToday = $task->due_date?->isToday() ?? false;
    $isTomorrow = $task->due_date?->isTomorrow() ?? false;
    $dueDateColor = $isOverdue ? 'var(--nx-danger)' : ($isToday || $isTomorrow ? 'var(--nx-warning)' : 'var(--nx-muted)');
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $priorityColor = $task->priority?->color() ?? null;
    $edgeColor = match (true) {
        $isDone      => 'var(--nx-success)',
        $isDiscarded => 'var(--nx-muted)',
        $isOverdue   => 'var(--nx-danger)',
        $task->is_frog => 'var(--nx-success)',
        default => $priorityColor ?? 'var(--nx-accent)',
    };
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        @php
            $breadcrumbSource = match($referrer ?? null) {
                'project' => $task->project ? ['label' => $task->project->name, 'href' => route('planner.projects.show', ['plannerProject' => $task->project->id])] : ['label' => 'Meine Aufgaben', 'href' => route('planner.my-tasks')],
                'frog' => ['label' => 'Frösche', 'href' => route('planner.frog-tasks')],
                'hygiene' => ['label' => 'Hygiene', 'href' => route('planner.hygiene')],
                'completed' => ['label' => 'Erledigte Aufgaben', 'href' => route('planner.completed-tasks')],
                'delegated' => ['label' => 'Delegierte Aufgaben', 'href' => route('planner.delegated-tasks')],
                default => ['label' => 'Meine Aufgaben', 'href' => route('planner.my-tasks')],
            };
        @endphp
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            $breadcrumbSource,
            ($referrer !== 'project' && $task->project) ? ['label' => $task->project->name, 'href' => route('planner.projects.show', ['plannerProject' => $task->project->id])] : null,
            ['label' => Str::limit($task->title, 40)],
        ])">
            @can('update', $task)
                @if($this->isDirty())
                    <x-nx-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-nx-button>
                @endif
            @endcan

            {{-- Overflow-Menü mit selteneren Aktionen --}}
            @php
                $canMove = auth()->user()?->can('update', $task) ?? false;
                $hasOverflow = $printingAvailable || $canMove;
            @endphp
            @if($hasOverflow)
                <div x-data="{ open: false }" class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                        title="Mehr"
                    >
                        @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                    </button>
                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity.duration.100ms
                        @click.outside="open = false"
                        @keydown.escape.window="open = false"
                        class="absolute top-full right-0 mt-1 w-48 bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 py-1"
                    >
                        @if($canMove)
                            <button
                                type="button"
                                wire:click="openMoveModal"
                                @click="open = false"
                                class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                            >
                                @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-[var(--nx-muted)]')
                                <span>Verschieben</span>
                            </button>
                        @endif
                        @if($printingAvailable)
                            <button
                                type="button"
                                wire:click="printTask()"
                                @click="open = false"
                                class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                            >
                                @svg('heroicon-o-printer', 'w-4 h-4 text-[var(--nx-muted)]')
                                <span>Drucken</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            @can('delete', $task)
                <x-nx-button variant="danger" size="sm" wire:click="deleteTask" wire:confirm="Wirklich löschen?">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    <span>Löschen</span>
                </x-nx-button>
            @endcan
        </x-ui-page-actionbar>
    </x-slot>


    {{-- Linke Page-Sidebar: Task-Zustand / Meta (read-only) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Zustand" icon="heroicon-o-information-circle" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--nx-bg)]">
                <section class="rounded-lg bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] p-3">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2.5">Zustand</h3>
                    <dl class="space-y-2 text-[11px] m-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="inline-flex items-center gap-1.5 text-[var(--nx-muted)]">@svg('heroicon-o-clock', 'w-3.5 h-3.5 opacity-70') Angelegt</dt>
                            <dd class="m-0 tabular-nums text-[var(--nx-text)]">{{ $task->created_at->format('d.m.Y') }}</dd>
                        </div>
                        @if(($task->postpone_count ?? 0) > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="inline-flex items-center gap-1.5 text-[var(--nx-muted)]">@svg('heroicon-o-arrow-path', 'w-3.5 h-3.5 opacity-70') Verschoben</dt>
                                <dd class="m-0 tabular-nums text-[var(--nx-text)]">{{ $task->postpone_count }}×</dd>
                            </div>
                        @endif
                        @if($task->original_due_date)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="inline-flex items-center gap-1.5 text-[var(--nx-muted)]">@svg('heroicon-o-calendar', 'w-3.5 h-3.5 opacity-70') Ursprünglich</dt>
                                <dd class="m-0 tabular-nums text-[var(--nx-text)]">{{ $task->original_due_date->format('d.m.Y') }}</dd>
                            </div>
                        @endif
                        @if($task->team)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="inline-flex items-center gap-1.5 text-[var(--nx-muted)]">@svg('heroicon-o-user-group', 'w-3.5 h-3.5 opacity-70') Team</dt>
                                <dd class="m-0 truncate text-[var(--nx-text)]">{{ $task->team->name }}</dd>
                            </div>
                        @endif
                        @if($this->contextFileCount > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="inline-flex items-center gap-1.5 text-[var(--nx-muted)]">@svg('heroicon-o-paper-clip', 'w-3.5 h-3.5 opacity-70') Anhänge</dt>
                                <dd class="m-0 tabular-nums text-[var(--nx-text)]">{{ $this->contextFileCount }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-4" background="bg-[color:var(--nx-bg)]">

            {{-- HERO --}}
            <div class="relative rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                <div class="absolute top-3 bottom-3 left-2 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></div>

                <div class="p-5 pl-7">
                    <div class="flex items-start gap-4">
                        {{-- Done checkbox --}}
                        <button
                            type="button"
                            wire:click="toggleDone"
                            @if($isDiscarded) disabled @endif
                            class="flex-shrink-0 mt-1 w-8 h-8 rounded-full border-2 flex items-center justify-center transition-all duration-200 cursor-pointer
                                {{ $isDone
                                    ? 'bg-[var(--nx-success)] border-[var(--nx-success)] text-white'
                                    : ($isDiscarded
                                        ? 'bg-[color:var(--nx-hover)] border-[color:var(--nx-line-strong)] text-[color:var(--nx-faint)] cursor-not-allowed'
                                        : 'border-[var(--nx-line-strong)] text-transparent hover:border-[var(--nx-success)] hover:text-[var(--nx-success)]') }}"
                            title="{{ $isDiscarded ? 'Verworfen — kann nicht umgeschaltet werden' : ($isDone ? 'Als offen markieren' : 'Als erledigt markieren') }}"
                        >
                            @svg($isDiscarded ? 'heroicon-o-x-mark' : 'heroicon-s-check', 'w-4 h-4')
                        </button>

                        <div class="flex-1 min-w-0">
                            <x-nx-input-text
                                name="task.title"
                                label=""
                                wire:model.live.debounce.1000ms="task.title"
                                :value="$task->title"
                                placeholder="Aufgabentitel eingeben..."
                                required
                                :errorKey="'task.title'"
                                class="!text-2xl !font-bold !border-none !shadow-none !ring-0 !p-0 !bg-transparent text-[var(--nx-text)] tracking-tight"
                            />

                            {{-- Context line --}}
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-[var(--nx-muted)] mt-2">
                                @if($task->project)
                                    <a href="{{ route('planner.projects.show', ['plannerProject' => $task->project->id]) }}" class="inline-flex items-center gap-1.5 hover:text-[var(--nx-accent)] transition-colors">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--nx-muted)' }};"></span>
                                        <span>{{ $task->project->name }}</span>
                                    </a>
                                    <span class="text-[var(--nx-line-strong)]">·</span>
                                @endif
                                @if($task->created_at)
                                    <span class="tabular-nums">{{ $task->created_at->format('d.m.Y') }}</span>
                                @endif
                                @if($task->user)
                                    <span class="text-[var(--nx-line-strong)]">·</span>
                                    <span>von {{ $task->user->fullname ?? $task->user->name }}</span>
                                @endif
                                @if($spValue)
                                    <span class="text-[var(--nx-line-strong)]">·</span>
                                    <span class="tabular-nums">{{ $spValue }} SP</span>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- EIGENSCHAFTEN — Notion-Property-Block (aus der alten linken Sidebar) --}}
            @php
                $lcMeta = match(true) {
                    $isDone      => ['dot' => 'bg-[var(--nx-success)]', 'label' => 'Erledigt'],
                    $isDiscarded => ['dot' => 'bg-[color:var(--nx-faint)]', 'label' => 'Verworfen'],
                    default      => ['dot' => 'bg-[var(--nx-accent)]', 'label' => 'Aktiv'],
                };
            @endphp
            <section class="rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] p-2">
                {{-- Status --}}
                <x-nx-property-row icon="heroicon-o-check-circle" label="Status">
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="toggleDone" @if($isDiscarded) disabled @endif
                            class="inline-flex items-center gap-1.5 rounded-[6px] px-2 py-1 transition-colors hover:bg-[color:var(--nx-hover)] {{ $isDiscarded ? 'opacity-60 cursor-not-allowed' : '' }}">
                            <span class="w-2 h-2 rounded-full {{ $lcMeta['dot'] }}"></span>
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $lcMeta['label'] }}</span>
                        </button>
                        @if(!$isDone && !$isDiscarded)
                            <button type="button" wire:click="discardTask"
                                wire:confirm="Aufgabe wirklich verwerfen? Sie wird nicht mehr bearbeitet und kann nicht zurückgeholt werden."
                                class="rounded-[6px] px-2 py-1 text-xs text-[color:var(--nx-muted)] transition-colors hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)]"
                                title="Aufgabe verwerfen — Terminal-Zustand">Verwerfen</button>
                        @endif
                    </div>
                </x-nx-property-row>

                {{-- Priorität --}}
                <x-nx-property-row icon="heroicon-o-flag" label="Priorität">
                    <x-nx-input-select name="task.priority" label="" size="sm"
                        :options="\Platform\Planner\Enums\TaskPriority::cases()" optionValue="value" optionLabel="label"
                        :nullable="false" wire:model.live="task.priority" />
                </x-nx-property-row>

                {{-- Verantwortlich --}}
                <x-nx-property-row icon="heroicon-o-user" label="Verantwortlich">
                    <x-nx-input-select name="task.user_in_charge_id" label="" size="sm"
                        :options="$teamUsers" optionValue="id" optionLabel="name"
                        :nullable="true" nullLabel="– Niemand –" wire:model.live="task.user_in_charge_id" />
                </x-nx-property-row>

                {{-- Fällig --}}
                <x-nx-property-row icon="heroicon-o-calendar-days" label="Fällig">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="openDueDateModal"
                            class="inline-flex items-center gap-1.5 rounded-[6px] px-2 py-1 transition-colors hover:bg-[color:var(--nx-hover)]"
                            style="color: {{ $task->due_date ? $dueDateColor : 'var(--nx-muted)' }}">
                            @if($task->due_date)
                                <span class="font-medium tabular-nums">{{ $task->due_date->format('d.m.Y H:i') }}</span>
                            @else
                                <span>Kein Datum</span>
                            @endif
                        </button>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="setQuickDueDate('today')" class="rounded border border-[color:var(--nx-line)] px-1.5 py-0.5 text-[10px] text-[color:var(--nx-muted)] transition-colors hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)]">Heute</button>
                            <button type="button" wire:click="setQuickDueDate('tomorrow')" class="rounded border border-[color:var(--nx-line)] px-1.5 py-0.5 text-[10px] text-[color:var(--nx-muted)] transition-colors hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)]">Morgen</button>
                            <button type="button" wire:click="setQuickDueDate('next_week')" class="rounded border border-[color:var(--nx-line)] px-1.5 py-0.5 text-[10px] text-[color:var(--nx-muted)] transition-colors hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)]">+1W</button>
                        </div>
                    </div>
                </x-nx-property-row>

                {{-- Story Points --}}
                <x-nx-property-row icon="heroicon-o-hashtag" label="Story Points">
                    <x-nx-input-select name="task.story_points" label="" size="sm"
                        :options="\Platform\Planner\Enums\TaskStoryPoints::cases()" optionValue="value" optionLabel="label"
                        :nullable="true" nullLabel="–" wire:model.live="task.story_points" />
                </x-nx-property-row>

                {{-- Frosch --}}
                <x-nx-property-row icon="heroicon-o-sparkles" label="Frosch">
                    <button type="button" wire:click="toggleFrog"
                        class="inline-flex items-center gap-1.5 rounded-[6px] px-2 py-1 transition-colors hover:bg-[color:var(--nx-hover)]">
                        @if($task->is_frog)<span class="text-[color:var(--nx-text)]">🐸 Ja</span>@else<span class="text-[color:var(--nx-muted)]">Nein</span>@endif
                    </button>
                </x-nx-property-row>

            </section>

            {{-- ANMERKUNG --}}
            <section class="rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                <div class="px-5 py-3 border-b border-[var(--nx-line-strong)]/30 flex items-center gap-2">
                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--nx-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Anmerkung</h2>
                    <span title="Verschlüsselt gespeichert" class="ml-auto text-[var(--nx-muted)]">
                        @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                    </span>
                </div>
                <div class="p-5">
                    <x-nx-input-textarea
                        name="description"
                        label=""
                        wire:model.live.debounce.1000ms="description"
                        :placeholder="empty($description) ? 'Zusätzliche Notizen und Informationen zur Aufgabe (optional)' : ''"
                        rows="6"
                        :errorKey="'description'"
                    />
                </div>
            </section>

            {{-- DEFINITION OF DONE --}}
            <section class="rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                <div class="px-5 py-3 border-b border-[var(--nx-line-strong)]/30 flex items-center gap-2">
                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--nx-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Definition of Done</h2>
                    <span title="Verschlüsselt gespeichert" class="text-[var(--nx-muted)]">
                        @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                    </span>
                    @if(count($dodItems) > 0)
                        <div class="ml-auto flex items-center gap-2">
                            <span class="text-[11px] font-medium text-[var(--nx-muted)] tabular-nums">
                                {{ $this->dodProgress['checked'] }}/{{ $this->dodProgress['total'] }}
                            </span>
                            <div class="w-24 h-1.5 bg-[var(--nx-line)] rounded-full overflow-hidden">
                                <div
                                    class="h-full transition-all duration-300 rounded-full {{ $this->dodProgress['isComplete'] ? 'bg-[var(--nx-success)]' : 'bg-[var(--nx-accent)]' }}"
                                    style="width: {{ $this->dodProgress['percentage'] }}%"
                                ></div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="p-5">
                    <div class="space-y-1.5">
                        @forelse($dodItems as $index => $item)
                            <div
                                class="group relative flex items-start gap-3 p-2.5 pl-4 rounded-lg border border-[color:var(--nx-line)] hover:border-[var(--nx-accent)]/40 hover:bg-[var(--nx-bg)] transition-all"
                                wire:key="dod-item-{{ $index }}"
                            >
                                <span class="absolute top-2 bottom-2 left-1 w-[2px] rounded-full {{ $item['checked'] ? 'bg-[var(--nx-success)]' : 'bg-[var(--nx-line-strong)]' }}"></span>
                                <button
                                    type="button"
                                    wire:click="toggleDodItem({{ $index }})"
                                    class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 transition-all flex items-center justify-center {{ $item['checked'] ? 'bg-[var(--nx-success)] border-[var(--nx-success)] text-white' : 'border-[var(--nx-line-strong)] hover:border-[var(--nx-accent)]' }}"
                                >
                                    @if($item['checked'])
                                        @svg('heroicon-s-check', 'w-3 h-3')
                                    @endif
                                </button>
                                <div class="flex-1 min-w-0">
                                    <input
                                        type="text"
                                        value="{{ $item['text'] }}"
                                        wire:blur="updateDodItemText({{ $index }}, $event.target.value)"
                                        class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 focus:outline-none {{ $item['checked'] ? 'line-through text-[var(--nx-muted)]' : 'text-[var(--nx-text)]' }}"
                                        placeholder="DoD-Kriterium eingeben..."
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="removeDodItem({{ $index }})"
                                    wire:confirm="Möchten Sie diesen DoD-Punkt wirklich entfernen?"
                                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--nx-muted)] hover:text-[var(--nx-danger)] hover:bg-[rgba(224,49,49,.08)] transition-all"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-6 text-[var(--nx-muted)]">
                                <div class="flex justify-center mb-2">
                                    @svg('heroicon-o-clipboard-document-check', 'w-8 h-8 opacity-40')
                                </div>
                                <p class="text-sm m-0">Noch keine DoD-Kriterien definiert</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Add DoD item --}}
                    <div class="mt-3" x-data="{ newDodText: '', isAdding: false }">
                        <template x-if="!isAdding">
                            <button
                                type="button"
                                @click="isAdding = true; $nextTick(() => $refs.newDodInput?.focus())"
                                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg border border-dashed border-[var(--nx-line-strong)] text-[var(--nx-muted)] hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/5 transition-all"
                            >
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>DoD-Kriterium hinzufügen</span>
                            </button>
                        </template>
                        <template x-if="isAdding">
                            <div class="flex items-center gap-2 p-2 rounded-lg border border-[var(--nx-accent)]/60 bg-[var(--nx-accent)]/5">
                                <input
                                    type="text"
                                    x-ref="newDodInput"
                                    x-model="newDodText"
                                    @keydown.enter.prevent="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; }"
                                    @keydown.escape="isAdding = false; newDodText = ''"
                                    @blur="if(!newDodText.trim()) { isAdding = false; }"
                                    class="flex-1 bg-transparent border-none p-1 text-sm focus:ring-0 focus:outline-none text-[var(--nx-text)]"
                                    placeholder="Neues DoD-Kriterium eingeben..."
                                />
                                <button
                                    type="button"
                                    @click="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; } isAdding = false;"
                                    class="flex-shrink-0 p-1 rounded text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/10 transition-colors"
                                >
                                    @svg('heroicon-o-check', 'w-5 h-5')
                                </button>
                                <button
                                    type="button"
                                    @click="isAdding = false; newDodText = ''"
                                    class="flex-shrink-0 p-1 rounded text-[var(--nx-muted)] hover:text-[var(--nx-danger)] transition-colors"
                                >
                                    @svg('heroicon-o-x-mark', 'w-5 h-5')
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            {{-- ABHÄNGIGKEITEN (blockiert-von) --}}
            <section class="rounded-xl bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] shadow-[var(--nx-shadow-card)] overflow-hidden">
                <div class="px-5 py-3 border-b border-[var(--nx-line-strong)]/30 flex items-center gap-2">
                    @svg('heroicon-o-lock-closed', 'w-4 h-4 text-[var(--nx-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] m-0">Abhängigkeiten</h2>
                    @php $openBlockers = collect($this->blockers)->where('open', true)->count(); @endphp
                    @if($openBlockers > 0 || $this->slotGateBlocked)
                        <span class="ml-auto inline-flex items-center gap-1 rounded-full bg-[var(--nx-warning)]/15 px-2 py-0.5 text-[11px] font-medium text-[var(--nx-warning)]">
                            🔒 blockiert
                        </span>
                    @endif
                </div>
                <div class="p-5 space-y-4">
                    {{-- Slot-Gate-Hinweis --}}
                    @if($this->slotGateBlocked)
                        <div class="text-[12px] text-[var(--nx-muted)] rounded-lg bg-[color:var(--nx-hover)] px-3 py-2">
                            Diese Spalte ist auf „Erst nach vorherigen Spalten" gestellt — die Aufgabe wird erst
                            ausführbar, wenn alle Aufgaben in davorliegenden Spalten erledigt/verworfen sind.
                        </div>
                    @endif

                    {{-- Liste der Vorgänger --}}
                    @forelse($this->blockers as $b)
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $b['open'] ? 'bg-[var(--nx-warning)]' : 'bg-[var(--nx-success)]' }}"></span>
                            <a href="{{ route('planner.tasks.show', $b['id']) }}" wire:navigate class="min-w-0 flex-1 truncate text-sm text-[color:var(--nx-text)] hover:underline">
                                {{ $b['title'] }}
                            </a>
                            <span class="text-[11px] text-[var(--nx-muted)]">{{ $b['label'] }}</span>
                            <button type="button" wire:click="removeBlocker({{ $b['id'] }})"
                                title="Abhängigkeit entfernen"
                                class="text-[var(--nx-muted)] hover:text-[var(--nx-danger)] transition-colors">
                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <p class="text-[12px] text-[var(--nx-muted)] m-0">Keine Vorgänger. Diese Aufgabe wartet auf nichts.</p>
                    @endforelse

                    {{-- Vorgänger hinzufügen (projekt-intern) --}}
                    <div class="pt-1">
                        <x-nx-input-text
                            name="blockerSearch"
                            label=""
                            wire:model.live.debounce.300ms="blockerSearch"
                            placeholder="Vorgänger suchen (nur dieses Projekt)…"
                        />
                        @if(trim($this->blockerSearch) !== '')
                            <div class="mt-2 rounded-lg border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)] overflow-hidden">
                                @forelse($this->blockerCandidates as $c)
                                    <button type="button" wire:click="addBlocker({{ $c->id }})"
                                        class="w-full text-left px-3 py-2 text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition-colors flex items-center gap-2">
                                        @svg('heroicon-o-plus', 'w-3.5 h-3.5 text-[var(--nx-muted)] flex-shrink-0')
                                        <span class="truncate">{{ $c->title }}</span>
                                    </button>
                                @empty
                                    <p class="px-3 py-2 text-[12px] text-[var(--nx-muted)] m-0">Keine passenden Aufgaben.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- EXTRA FIELDS --}}
            <x-core-extra-fields-section
                :definitions="$this->extraFieldDefinitions"
                :model="$task"
            />
    </x-ui-page-container>

    {{-- Print Modal --}}
    <livewire:planner.print-modal />

    {{-- Move Task Modal --}}
    <x-nx-modal size="md" wire:model="moveModalOpen" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                    @svg('heroicon-o-arrows-right-left', 'w-5 h-5 text-[var(--nx-accent)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">Aufgabe verschieben</h3>
                    <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5">Projekt und Spalte auswählen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Zielprojekt</h4>
                <x-nx-input-select
                    name="targetProjectId"
                    label=""
                    :options="$projectMoveOptions"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– Projekt wählen –"
                    wire:model.live="targetProjectId"
                />
                <p class="mt-1.5 text-[11px] text-[var(--nx-muted)]">Nur Projekte mit Berechtigung werden angezeigt.</p>
            </section>

            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-2">Spalte</h4>
                <x-nx-input-select
                    name="targetSlotId"
                    label=""
                    :options="$projectSlotOptions"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="Backlog (keine Spalte)"
                    wire:model.live="targetSlotId"
                    :disabled="!$targetProjectId"
                />
                @if(!$targetProjectId)
                    <p class="mt-1.5 text-[11px] text-[var(--nx-muted)] italic">Wähle erst ein Zielprojekt.</p>
                @endif
            </section>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="secondary" size="sm" wire:click="closeMoveModal">Abbrechen</x-nx-button>
                <x-nx-button
                    variant="primary"
                    size="sm"
                    wire:click="moveTaskToProject"
                    wire:loading.attr="disabled"
                    wire:target="moveTaskToProject"
                    :disabled="!$targetProjectId"
                >
                    @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5')
                    <span>Verschieben</span>
                </x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>

    {{-- Due Date Modal --}}
    <x-nx-modal size="md" wire:model="dueDateModalShow" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--nx-accent)]/10 flex-shrink-0">
                    @svg('heroicon-o-calendar-days', 'w-5 h-5 text-[var(--nx-accent)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--nx-text)] m-0 leading-tight">Fälligkeitsdatum</h3>
                    <p class="text-[12px] text-[var(--nx-muted)] m-0 mt-0.5">Datum und Uhrzeit festlegen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">

            {{-- Quick-Picks --}}
            <div class="flex flex-wrap gap-1.5">
                <button type="button" wire:click="setQuickDueDate('today')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--nx-line-strong)] text-[var(--nx-text)] hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/5 transition-all">
                    @svg('heroicon-o-bolt', 'w-3 h-3 opacity-60')
                    Heute
                </button>
                <button type="button" wire:click="setQuickDueDate('tomorrow')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--nx-line-strong)] text-[var(--nx-text)] hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/5 transition-all">
                    @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-60')
                    Morgen
                </button>
                <button type="button" wire:click="setQuickDueDate('next_week')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--nx-line-strong)] text-[var(--nx-text)] hover:border-[var(--nx-accent)]/60 hover:text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/5 transition-all">
                    @svg('heroicon-o-forward', 'w-3 h-3 opacity-60')
                    In einer Woche
                </button>
            </div>

            {{-- Kalender --}}
            <div class="rounded-xl border border-[color:var(--nx-line)] bg-[var(--nx-bg)]/40 p-3">
                {{-- Monatsnavigation --}}
                <div class="flex items-center justify-between mb-2">
                    <button type="button" wire:click="previousMonth" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[color:var(--nx-surface)] transition-colors" title="Vorheriger Monat">
                        @svg('heroicon-o-chevron-left', 'w-4 h-4')
                    </button>
                    <h2 class="text-sm font-semibold text-[var(--nx-text)] m-0 tabular-nums">{{ $this->calendarMonthName }}</h2>
                    <button type="button" wire:click="nextMonth" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[color:var(--nx-surface)] transition-colors" title="Nächster Monat">
                        @svg('heroicon-o-chevron-right', 'w-4 h-4')
                    </button>
                </div>

                {{-- Wochentage --}}
                <div class="grid grid-cols-7 text-center text-[10px] font-semibold uppercase tracking-wider text-[var(--nx-muted)] mb-1">
                    <div>Mo</div><div>Di</div><div>Mi</div><div>Do</div><div>Fr</div><div>Sa</div><div>So</div>
                </div>

                {{-- Tage-Grid --}}
                <div class="grid grid-cols-7 gap-0.5">
                    @foreach($this->calendarDays as $day)
                        <button
                            type="button"
                            wire:click="selectDate('{{ $day['date'] }}')"
                            @class([
                                'aspect-square inline-flex items-center justify-center text-[12px] rounded-md transition-all tabular-nums',
                                'text-[var(--nx-muted)]/40' => !$day['isCurrentMonth'],
                                'text-[var(--nx-text)] hover:bg-[color:var(--nx-surface)] hover:shadow-[var(--nx-shadow-card)]' => $day['isCurrentMonth'] && !$day['isToday'] && !$day['isSelected'],
                                'font-semibold ring-1 ring-[var(--nx-accent)]/50 text-[var(--nx-accent)] hover:bg-[var(--nx-accent)]/5' => $day['isToday'] && !$day['isSelected'],
                                'font-bold text-white bg-[var(--nx-accent)] shadow-[var(--nx-shadow-card)]' => $day['isSelected'],
                            ])
                        >
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Uhrzeit --}}
            <div class="rounded-xl border border-[color:var(--nx-line)] p-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="inline-flex items-center gap-2 text-[11px] text-[var(--nx-muted)]">
                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                        <span class="font-semibold uppercase tracking-wider">Uhrzeit</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <select wire:model.live="selectedHour" class="w-16 px-2 py-1 text-sm rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--nx-accent)]/20 focus:border-[var(--nx-accent)] tabular-nums">
                            @for($h = 0; $h < 24; $h++)
                                <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                        <span class="text-sm font-semibold text-[var(--nx-muted)]">:</span>
                        <select wire:model.live="selectedMinute" class="w-16 px-2 py-1 text-sm rounded-md border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--nx-accent)]/20 focus:border-[var(--nx-accent)] tabular-nums">
                            @foreach([0, 15, 30, 45] as $minute)
                                <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Ausgewählte Zusammenfassung --}}
            @if($selectedDate)
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--nx-accent)]/5 border border-[var(--nx-accent)]/20 text-[12px]">
                    @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5 text-[var(--nx-accent)] flex-shrink-0')
                    <span class="text-[var(--nx-muted)]">Ausgewählt:</span>
                    <span class="font-semibold text-[var(--nx-accent)] flex-1">
                        {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                        @if($selectedTime) · {{ $selectedTime }} Uhr @endif
                    </span>
                </div>
            @endif

            {{-- Datum entfernen — quiet inline link --}}
            @if($task->due_date)
                <div class="text-right">
                    <button type="button" wire:click="clearDueDate" class="inline-flex items-center gap-1 text-[11px] text-[var(--nx-muted)] hover:text-[var(--nx-danger)] transition-colors">
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                        Bestehendes Datum entfernen
                    </button>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="secondary" size="sm" wire:click="closeDueDateModal">Abbrechen</x-nx-button>
                <button
                    type="button"
                    wire:click="saveDueDate"
                    wire:loading.attr="disabled"
                    wire:target="saveDueDate"
                    wire:disabled="!selectedDate"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-[var(--nx-accent)] text-white hover:bg-[var(--nx-accent)]/90 focus:outline-none focus:ring-2 focus:ring-[var(--nx-accent)]/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                >
                    <span wire:loading.remove wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Speichern
                    </span>
                    <span wire:loading wire:target="saveDueDate" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Speichern...
                    </span>
                </button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
