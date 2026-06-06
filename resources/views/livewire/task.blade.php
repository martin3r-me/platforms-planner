@php
    $isOverdue = $task->due_date && $task->due_date->isPast() && !$task->is_done;
    $isToday = $task->due_date?->isToday() ?? false;
    $isTomorrow = $task->due_date?->isTomorrow() ?? false;
    $dueDateColor = $isOverdue ? 'var(--planner-status-overdue)' : ($isToday || $isTomorrow ? '#f59e0b' : 'var(--ui-muted)');
    $spValue = is_object($task->story_points) ? $task->story_points->points() : $task->story_points;
    $priorityColor = $task->priority?->color() ?? null;
    $edgeColor = $task->is_done
        ? 'var(--planner-status-done)'
        : ($isOverdue ? 'var(--planner-status-overdue)' : ($task->is_frog ? 'var(--planner-frog)' : ($priorityColor ?? 'var(--planner-status-active)')));
@endphp

<x-ui-page>
    @include('planner::partials.planner-tokens')

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
                    <x-ui-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-ui-button>
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
                        class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
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
                        class="absolute top-full right-0 mt-1 w-48 bg-white border border-[var(--ui-border)] rounded-lg shadow-lg z-30 py-1"
                    >
                        @if($canMove)
                            <button
                                type="button"
                                wire:click="openMoveModal"
                                @click="open = false"
                                class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                @svg('heroicon-o-arrows-right-left', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span>Verschieben</span>
                            </button>
                        @endif
                        @if($printingAvailable)
                            <button
                                type="button"
                                wire:click="printTask()"
                                @click="open = false"
                                class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                @svg('heroicon-o-printer', 'w-4 h-4 text-[var(--ui-muted)]')
                                <span>Drucken</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            @can('delete', $task)
                <x-ui-confirm-button
                    action="deleteTask"
                    text="Löschen"
                    confirmText="Wirklich löschen?"
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            @endcan
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Left sidebar: Task-Eigenschaften --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Eigenschaften" icon="heroicon-o-adjustments-horizontal" width="w-72" :defaultOpen="true">
            <div class="p-4 space-y-4 bg-[var(--ui-muted-5)]">

                {{-- STATUS & PRIORITÄT --}}
                <section class="rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] px-3 pt-3 pb-1.5">Status & Priorität</h3>

                    <button type="button" wire:click="toggleDone" class="w-full flex items-center justify-between py-2 px-3 hover:bg-[var(--ui-muted-5)] transition-colors text-[11px]">
                        <span class="text-[var(--ui-muted)]">Status</span>
                        <span class="inline-flex items-center gap-1.5 font-medium">
                            <span class="w-2 h-2 rounded-full {{ $task->is_done ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-status-active)]' }}"></span>
                            <span class="text-[var(--ui-secondary)]">{{ $task->is_done ? 'Erledigt' : 'Offen' }}</span>
                        </span>
                    </button>

                    <div class="py-2 px-3 border-t border-[var(--ui-border)]/30 text-[11px]">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[var(--ui-muted)]">Priorität</span>
                            @if($priorityColor)
                                <span class="w-2 h-2 rounded-full" style="background-color: {{ $priorityColor }}"></span>
                            @endif
                        </div>
                        <x-ui-input-select
                            name="task.priority"
                            label=""
                            :options="\Platform\Planner\Enums\TaskPriority::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model.live="task.priority"
                        />
                    </div>

                    <button type="button" wire:click="toggleFrog" class="w-full flex items-center justify-between py-2 px-3 border-t border-[var(--ui-border)]/30 hover:bg-[var(--ui-muted-5)] transition-colors text-[11px]">
                        <span class="text-[var(--ui-muted)]">Frosch</span>
                        <span class="font-medium text-[var(--ui-secondary)]">
                            @if($task->is_frog) 🐸 Ja @else Nein @endif
                        </span>
                    </button>
                </section>

                {{-- PLANUNG --}}
                <section class="rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] px-3 pt-3 pb-1.5">Planung</h3>

                    <div class="py-2 px-3 text-[11px]">
                        <span class="text-[var(--ui-muted)] block mb-1.5">Verantwortlich</span>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label=""
                            :options="$teamUsers"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Niemand –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>

                    <button type="button" wire:click="openDueDateModal" class="w-full flex items-center justify-between py-2 px-3 border-t border-[var(--ui-border)]/30 hover:bg-[var(--ui-muted-5)] transition-colors text-[11px]">
                        <span class="text-[var(--ui-muted)]">Fällig</span>
                        <span class="font-medium" style="color: {{ $dueDateColor }}">
                            @if($task->due_date)
                                {{ $task->due_date->format('d.m.Y H:i') }}
                            @else
                                <span class="text-[var(--ui-muted)]/60">Kein Datum</span>
                            @endif
                        </span>
                    </button>

                    <div class="flex items-center gap-1 px-3 pb-2 pt-1">
                        <button type="button" wire:click="setQuickDueDate('today')" class="flex-1 px-1.5 py-0.5 text-[10px] rounded border border-[var(--ui-border)]/40 text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/60 hover:text-[var(--ui-primary)] transition-colors">Heute</button>
                        <button type="button" wire:click="setQuickDueDate('tomorrow')" class="flex-1 px-1.5 py-0.5 text-[10px] rounded border border-[var(--ui-border)]/40 text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/60 hover:text-[var(--ui-primary)] transition-colors">Morgen</button>
                        <button type="button" wire:click="setQuickDueDate('next_week')" class="flex-1 px-1.5 py-0.5 text-[10px] rounded border border-[var(--ui-border)]/40 text-[var(--ui-muted)] hover:border-[var(--ui-primary)]/60 hover:text-[var(--ui-primary)] transition-colors">+1W</button>
                    </div>

                    <div class="py-2 px-3 border-t border-[var(--ui-border)]/30 text-[11px]">
                        <span class="text-[var(--ui-muted)] block mb-1.5">Story Points</span>
                        <x-ui-input-select
                            name="task.story_points"
                            label=""
                            :options="\Platform\Planner\Enums\TaskStoryPoints::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="true"
                            nullLabel="–"
                            wire:model.live="task.story_points"
                        />
                    </div>
                </section>

                {{-- DETAILS --}}
                <section class="rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] px-3 pt-3 pb-1.5">Details</h3>
                    <dl class="divide-y divide-[var(--ui-border)]/30 text-[11px]">
                        @if($task->team)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-3">
                                <dt class="text-[var(--ui-muted)]">Team</dt>
                                <dd class="text-[var(--ui-secondary)] m-0 truncate">{{ $task->team->name }}</dd>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-3 py-1.5 px-3">
                            <dt class="text-[var(--ui-muted)]">Erstellt</dt>
                            <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">{{ $task->created_at->format('d.m.Y') }}</dd>
                        </div>
                        @if(($task->postpone_count ?? 0) > 0)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-3">
                                <dt class="text-[var(--ui-muted)]">Verschoben</dt>
                                <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">{{ $task->postpone_count }}×</dd>
                            </div>
                        @endif
                        @if($task->original_due_date)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-3">
                                <dt class="text-[var(--ui-muted)]">Ursprünglich</dt>
                                <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">{{ $task->original_due_date->format('d.m.Y') }}</dd>
                            </div>
                        @endif
                        @if($this->contextFileCount > 0)
                            <div class="flex items-baseline justify-between gap-3 py-1.5 px-3">
                                <dt class="text-[var(--ui-muted)]">Anhänge</dt>
                                <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">{{ $this->contextFileCount }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Right sidebar: Aktivitäten --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" icon="heroicon-o-bolt" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-3 bg-[var(--ui-muted-5)]">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] px-1">Letzte Aktivitäten</div>
                @forelse(($activities ?? []) as $activity)
                    <div class="p-3 rounded-lg bg-white border border-[var(--ui-border)]/40 shadow-sm">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <div class="text-[12px] font-medium text-[var(--ui-secondary)] leading-snug">
                                {{ $activity['title'] ?? 'Aktivität' }}
                            </div>
                            @if(($activity['type'] ?? null) === 'system')
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                                    @svg('heroicon-o-cog-6-tooth', 'w-2.5 h-2.5')
                                    System
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)]">
                            @svg('heroicon-o-clock', 'w-3 h-3 opacity-60')
                            <span>{{ $activity['time'] ?? '' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white border border-[var(--ui-border)]/40 mb-3">
                            @svg('heroicon-o-bolt', 'w-5 h-5 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-xs text-[var(--ui-muted)] m-0">Noch keine Aktivitäten</p>
                        <p class="text-[10px] text-[var(--ui-muted)] mt-1 m-0">Änderungen werden hier angezeigt</p>
                    </div>
                @endforelse
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="flex-1 overflow-y-auto bg-[var(--ui-muted-5)]">
        <div class="p-6 space-y-5">

            {{-- HERO --}}
            <div class="relative rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="absolute top-3 bottom-3 left-2 w-[3px] rounded-full" style="background-color: {{ $edgeColor }};"></div>

                <div class="p-5 pl-7">
                    <div class="flex items-start gap-4">
                        {{-- Done checkbox --}}
                        <button
                            type="button"
                            wire:click="toggleDone"
                            class="flex-shrink-0 mt-1 w-8 h-8 rounded-full border-2 flex items-center justify-center transition-all duration-200 cursor-pointer
                                {{ $task->is_done
                                    ? 'bg-[var(--planner-status-done)] border-[var(--planner-status-done)] text-white'
                                    : 'border-[var(--ui-border)] text-transparent hover:border-[var(--planner-status-done)] hover:text-[var(--planner-status-done)]' }}"
                            title="{{ $task->is_done ? 'Als offen markieren' : 'Als erledigt markieren' }}"
                        >
                            @svg('heroicon-s-check', 'w-4 h-4')
                        </button>

                        <div class="flex-1 min-w-0">
                            <x-ui-input-text
                                name="task.title"
                                label=""
                                wire:model.live.debounce.1000ms="task.title"
                                :value="$task->title"
                                placeholder="Aufgabentitel eingeben..."
                                required
                                :errorKey="'task.title'"
                                class="!text-2xl !font-bold !border-none !shadow-none !ring-0 !p-0 !bg-transparent text-[var(--ui-secondary)] tracking-tight"
                            />

                            {{-- Context line --}}
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-[var(--ui-muted)] mt-2">
                                @if($task->project)
                                    <a href="{{ route('planner.projects.show', ['plannerProject' => $task->project->id]) }}" class="inline-flex items-center gap-1.5 hover:text-[var(--planner-status-active)] transition-colors">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->project->color ?? 'var(--ui-muted)' }};"></span>
                                        <span>{{ $task->project->name }}</span>
                                    </a>
                                    <span class="text-[var(--ui-border)]">·</span>
                                @endif
                                @if($task->created_at)
                                    <span class="tabular-nums">{{ $task->created_at->format('d.m.Y') }}</span>
                                @endif
                                @if($task->user)
                                    <span class="text-[var(--ui-border)]">·</span>
                                    <span>von {{ $task->user->fullname ?? $task->user->name }}</span>
                                @endif
                                @if($spValue)
                                    <span class="text-[var(--ui-border)]">·</span>
                                    <span class="tabular-nums">{{ $spValue }} SP</span>
                                @endif
                            </div>

                            {{-- Status pills --}}
                            @if($task->is_frog || $isOverdue || $task->is_done || $task->due_date)
                                <div class="flex flex-wrap items-center gap-1.5 mt-3">
                                    @if($task->is_done)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-done)] text-white">
                                            @svg('heroicon-s-check', 'w-3 h-3')
                                            Erledigt
                                        </span>
                                    @elseif($isOverdue)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-status-overdue)] text-white">
                                            @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                            {{ (int) $task->due_date->diffInDays(now()) }}d überfällig
                                        </span>
                                    @elseif($task->due_date)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full" style="background-color: color-mix(in srgb, {{ $dueDateColor }} 14%, white); color: {{ $dueDateColor }};">
                                            @svg('heroicon-o-clock', 'w-3 h-3')
                                            {{ $task->due_date->format('d.m.Y H:i') }}
                                        </span>
                                    @endif
                                    @if($task->is_frog)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-[var(--planner-frog)]/15 text-[var(--planner-frog)]">
                                            🐸 Frosch
                                        </span>
                                    @endif
                                    @if($task->priority)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium rounded-full" style="background-color: color-mix(in srgb, {{ $task->priority->color() }} 14%, white); color: {{ $task->priority->color() }};">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $task->priority->color() }};"></span>
                                            {{ $task->priority->label() }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ANMERKUNG --}}
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Anmerkung</h2>
                    <span title="Verschlüsselt gespeichert" class="ml-auto text-[var(--ui-muted)]">
                        @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                    </span>
                </div>
                <div class="p-5">
                    <x-ui-input-textarea
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
            <section class="rounded-xl bg-white border border-[var(--ui-border)]/40 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-[var(--ui-border)]/30 flex items-center gap-2">
                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4 text-[var(--ui-muted)]')
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Definition of Done</h2>
                    <span title="Verschlüsselt gespeichert" class="text-[var(--ui-muted)]">
                        @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                    </span>
                    @if(count($dodItems) > 0)
                        <div class="ml-auto flex items-center gap-2">
                            <span class="text-[11px] font-medium text-[var(--ui-muted)] tabular-nums">
                                {{ $this->dodProgress['checked'] }}/{{ $this->dodProgress['total'] }}
                            </span>
                            <div class="w-24 h-1.5 bg-[var(--planner-track)] rounded-full overflow-hidden">
                                <div
                                    class="h-full transition-all duration-300 rounded-full {{ $this->dodProgress['isComplete'] ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--planner-track-fill)]' }}"
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
                                class="group relative flex items-start gap-3 p-2.5 pl-4 rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--planner-status-active)]/40 hover:bg-[var(--ui-muted-5)] transition-all"
                                wire:key="dod-item-{{ $index }}"
                            >
                                <span class="absolute top-2 bottom-2 left-1 w-[2px] rounded-full {{ $item['checked'] ? 'bg-[var(--planner-status-done)]' : 'bg-[var(--ui-border)]' }}"></span>
                                <button
                                    type="button"
                                    wire:click="toggleDodItem({{ $index }})"
                                    class="flex-shrink-0 w-5 h-5 mt-0.5 rounded border-2 transition-all flex items-center justify-center {{ $item['checked'] ? 'bg-[var(--planner-status-done)] border-[var(--planner-status-done)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--planner-status-active)]' }}"
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
                                        class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 focus:outline-none {{ $item['checked'] ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }}"
                                        placeholder="DoD-Kriterium eingeben..."
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="removeDodItem({{ $index }})"
                                    wire:confirm="Möchten Sie diesen DoD-Punkt wirklich entfernen?"
                                    class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition-all"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-6 text-[var(--ui-muted)]">
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
                                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-medium rounded-lg border border-dashed border-[var(--ui-border)] text-[var(--ui-muted)] hover:border-[var(--planner-status-active)]/60 hover:text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5 transition-all"
                            >
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>DoD-Kriterium hinzufügen</span>
                            </button>
                        </template>
                        <template x-if="isAdding">
                            <div class="flex items-center gap-2 p-2 rounded-lg border border-[var(--planner-status-active)]/60 bg-[var(--planner-status-active)]/5">
                                <input
                                    type="text"
                                    x-ref="newDodInput"
                                    x-model="newDodText"
                                    @keydown.enter.prevent="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; }"
                                    @keydown.escape="isAdding = false; newDodText = ''"
                                    @blur="if(!newDodText.trim()) { isAdding = false; }"
                                    class="flex-1 bg-transparent border-none p-1 text-sm focus:ring-0 focus:outline-none text-[var(--ui-secondary)]"
                                    placeholder="Neues DoD-Kriterium eingeben..."
                                />
                                <button
                                    type="button"
                                    @click="if(newDodText.trim()) { $wire.addDodItem(newDodText); newDodText = ''; } isAdding = false;"
                                    class="flex-shrink-0 p-1 rounded text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/10 transition-colors"
                                >
                                    @svg('heroicon-o-check', 'w-5 h-5')
                                </button>
                                <button
                                    type="button"
                                    @click="isAdding = false; newDodText = ''"
                                    class="flex-shrink-0 p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                >
                                    @svg('heroicon-o-x-mark', 'w-5 h-5')
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            {{-- EXTRA FIELDS --}}
            <x-core-extra-fields-section
                :definitions="$this->extraFieldDefinitions"
                :model="$task"
            />
        </div>
    </div>

    {{-- Print Modal --}}
    <livewire:planner.print-modal />

    {{-- Move Task Modal --}}
    <x-ui-modal size="md" wire:model="moveModalOpen" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                    @svg('heroicon-o-arrows-right-left', 'w-5 h-5 text-[var(--planner-status-active)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Aufgabe verschieben</h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Projekt und Spalte auswählen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">
            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Zielprojekt</h4>
                <x-ui-input-select
                    name="targetProjectId"
                    label=""
                    :options="$projectMoveOptions"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="– Projekt wählen –"
                    wire:model.live="targetProjectId"
                />
                <p class="mt-1.5 text-[11px] text-[var(--ui-muted)]">Nur Projekte mit Berechtigung werden angezeigt.</p>
            </section>

            <section>
                <h4 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">Spalte</h4>
                <x-ui-input-select
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
                    <p class="mt-1.5 text-[11px] text-[var(--ui-muted)] italic">Wähle erst ein Zielprojekt.</p>
                @endif
            </section>
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeMoveModal">Abbrechen</x-ui-button>
                <x-ui-button
                    variant="primary"
                    size="sm"
                    wire:click="moveTaskToProject"
                    wire:loading.attr="disabled"
                    wire:target="moveTaskToProject"
                    :disabled="!$targetProjectId"
                >
                    @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5')
                    <span>Verschieben</span>
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    {{-- Due Date Modal --}}
    <x-ui-modal size="md" wire:model="dueDateModalShow" :backdropClosable="true" :escClosable="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-[var(--planner-status-active)]/10 flex-shrink-0">
                    @svg('heroicon-o-calendar-days', 'w-5 h-5 text-[var(--planner-status-active)]')
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 leading-tight">Fälligkeitsdatum</h3>
                    <p class="text-[12px] text-[var(--ui-muted)] m-0 mt-0.5">Datum und Uhrzeit festlegen</p>
                </div>
            </div>
        </x-slot>

        <div class="space-y-4">

            {{-- Quick-Picks --}}
            <div class="flex flex-wrap gap-1.5">
                <button type="button" wire:click="setQuickDueDate('today')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:border-[var(--planner-status-active)]/60 hover:text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5 transition-all">
                    @svg('heroicon-o-bolt', 'w-3 h-3 opacity-60')
                    Heute
                </button>
                <button type="button" wire:click="setQuickDueDate('tomorrow')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:border-[var(--planner-status-active)]/60 hover:text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5 transition-all">
                    @svg('heroicon-o-arrow-right', 'w-3 h-3 opacity-60')
                    Morgen
                </button>
                <button type="button" wire:click="setQuickDueDate('next_week')" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-full border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:border-[var(--planner-status-active)]/60 hover:text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5 transition-all">
                    @svg('heroicon-o-forward', 'w-3 h-3 opacity-60')
                    In einer Woche
                </button>
            </div>

            {{-- Kalender --}}
            <div class="rounded-xl border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/40 p-3">
                {{-- Monatsnavigation --}}
                <div class="flex items-center justify-between mb-2">
                    <button type="button" wire:click="previousMonth" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-white transition-colors" title="Vorheriger Monat">
                        @svg('heroicon-o-chevron-left', 'w-4 h-4')
                    </button>
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] m-0 tabular-nums">{{ $this->calendarMonthName }}</h2>
                    <button type="button" wire:click="nextMonth" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-white transition-colors" title="Nächster Monat">
                        @svg('heroicon-o-chevron-right', 'w-4 h-4')
                    </button>
                </div>

                {{-- Wochentage --}}
                <div class="grid grid-cols-7 text-center text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1">
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
                                'text-[var(--ui-muted)]/40' => !$day['isCurrentMonth'],
                                'text-[var(--ui-secondary)] hover:bg-white hover:shadow-sm' => $day['isCurrentMonth'] && !$day['isToday'] && !$day['isSelected'],
                                'font-semibold ring-1 ring-[var(--planner-status-active)]/50 text-[var(--planner-status-active)] hover:bg-[var(--planner-status-active)]/5' => $day['isToday'] && !$day['isSelected'],
                                'font-bold text-white bg-[var(--planner-status-active)] shadow-sm' => $day['isSelected'],
                            ])
                        >
                            <time datetime="{{ $day['date'] }}">{{ $day['day'] }}</time>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Uhrzeit --}}
            <div class="rounded-xl border border-[var(--ui-border)]/40 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="inline-flex items-center gap-2 text-[11px] text-[var(--ui-muted)]">
                        @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                        <span class="font-semibold uppercase tracking-wider">Uhrzeit</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <select wire:model.live="selectedHour" class="w-16 px-2 py-1 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--planner-status-active)]/20 focus:border-[var(--planner-status-active)] tabular-nums">
                            @for($h = 0; $h < 24; $h++)
                                <option value="{{ $h }}">{{ str_pad($h, 2, '0', STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                        <span class="text-sm font-semibold text-[var(--ui-muted)]">:</span>
                        <select wire:model.live="selectedMinute" class="w-16 px-2 py-1 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--planner-status-active)]/20 focus:border-[var(--planner-status-active)] tabular-nums">
                            @foreach([0, 15, 30, 45] as $minute)
                                <option value="{{ $minute }}">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Ausgewählte Zusammenfassung --}}
            @if($selectedDate)
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--planner-status-active)]/5 border border-[var(--planner-status-active)]/20 text-[12px]">
                    @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5 text-[var(--planner-status-active)] flex-shrink-0')
                    <span class="text-[var(--ui-muted)]">Ausgewählt:</span>
                    <span class="font-semibold text-[var(--planner-status-active)] flex-1">
                        {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                        @if($selectedTime) · {{ $selectedTime }} Uhr @endif
                    </span>
                </div>
            @endif

            {{-- Datum entfernen — quiet inline link --}}
            @if($task->due_date)
                <div class="text-right">
                    <button type="button" wire:click="clearDueDate" class="inline-flex items-center gap-1 text-[11px] text-[var(--ui-muted)] hover:text-[var(--planner-status-overdue)] transition-colors">
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                        Bestehendes Datum entfernen
                    </button>
                </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-ui-button variant="secondary-outline" size="sm" wire:click="closeDueDateModal">Abbrechen</x-ui-button>
                <button
                    type="button"
                    wire:click="saveDueDate"
                    wire:loading.attr="disabled"
                    wire:target="saveDueDate"
                    wire:disabled="!selectedDate"
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-md bg-[var(--planner-status-active)] text-white hover:bg-[var(--planner-status-active)]/90 focus:outline-none focus:ring-2 focus:ring-[var(--planner-status-active)]/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
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
    </x-ui-modal>
</x-ui-page>
