{{-- Root auf x-ui-page umstellen, damit volle Höhe/Sidebars korrekt funktionieren --}}
    @php 
        $completedTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        $allTasks = $groups->flatMap(fn($g) => $g->tasks);
        $openTasks = $groups->filter(fn($g) => !($g->isDoneGroup ?? false))->flatMap(fn($g) => $g->tasks);
        $doneTasks = $groups->filter(fn($g) => $g->isDoneGroup ?? false)->flatMap(fn($g) => $g->tasks);
        
        $statsOpen = [
            [
                'title' => 'Offen',
                'count' => $openTasks->count(),
                'icon' => 'clock',
                'variant' => 'warning'
            ],
            [
                'title' => 'Story Points',
                'count' => $openTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'sparkles',
                'variant' => 'warning'
            ],
            [
                'title' => 'Frösche',
                'count' => $openTasks->filter(fn($t) => $t->is_frog)->count(),
                'icon' => 'exclamation-triangle',
                'variant' => 'danger'
            ],
            [
                'title' => 'Überfällig',
                'count' => $openTasks->filter(fn($t) => $t->due_date && $t->due_date->isPast() && !$t->is_done)->count(),
                'icon' => 'exclamation-circle',
                'variant' => 'danger'
            ],
            [
                'title' => 'Ohne Fälligkeit',
                'count' => $openTasks->filter(fn($t) => !$t->due_date)->count(),
                'icon' => 'calendar',
                'variant' => 'neutral'
            ],
        ];
        
        $statsDone = [
            [
                'title' => 'Erledigt',
                'count' => $doneTasks->count(),
                'icon' => 'check-circle',
                'variant' => 'success'
            ],
            [
                'title' => 'Story Points',
                'count' => $doneTasks->sum(fn($t) => $t->story_points?->points() ?? 0),
                'icon' => 'sparkles',
                'variant' => 'success'
            ],
            [
                'title' => 'Verschiebungen',
                'count' => $allTasks->sum(fn($t) => $t->postpone_count ?? 0),
                'icon' => 'arrow-path',
                'variant' => 'secondary'
            ],
        ];
        $actions = [
            [
                'label' => '+ Neue Aufgabe',
                'variant' => 'success',
                'size' => 'sm',
                'wire_click' => 'createTask()'
            ],
            [
                'label' => '+ Neue Spalte',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => 'createProjectSlot'
            ]
        ];
        
        // Projekt-spezifische Buttons hinzufügen
        if(($project->project_type?->value ?? $project->project_type) === 'customer') {
            $actions[] = [
                'label' => 'Kunden',
                'variant' => 'primary',
                'size' => 'sm',
                'wire_click' => null,
                'onclick' => '$dispatch(\'open-modal-customer-project\', { projectId: ' . $project->id . ' })'
            ];
        }
        
        $actions[] = [
            'label' => 'Projekt-Einstellungen',
            'variant' => 'info',
            'size' => 'sm',
            'wire_click' => null,
            'onclick' => '$dispatch(\'open-modal-project-settings\', { projectId: ' . $project->id . ' })'
        ];
    @endphp

    {{-- Neues Layout via x-ui-page --}}
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list" />
        </x-slot>

        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Projekt-Übersicht" width="w-80" :defaultOpen="true">
                <div class="p-4 space-y-4">
                    {{-- Aktionen aus Navbar verschoben --}}
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Aktionen</h3>
                        <div class="flex items-center gap-2 flex-wrap">
                            @can('update', $project)
                                <x-ui-button variant="secondary" size="sm" wire:click="createProjectSlot">
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-square-2-stack','w-4 h-4')
                                        <span class="hidden sm:inline">Spalte</span>
                                    </span>
                                </x-ui-button>
                            @endcan
                            @can('update', $project)
                                <x-ui-button variant="secondary" size="sm" wire:click="createTask()">
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-plus','w-4 h-4')
                                        <span class="hidden sm:inline">Aufgabe</span>
                                    </span>
                                </x-ui-button>
                            @endcan
                            @can('settings', $project)
                                <x-ui-button variant="secondary-outline" size="sm" x-data @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })">
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-cog-6-tooth','w-4 h-4')
                                        <span class="hidden sm:inline">Einstellungen</span>
                                    </span>
                                </x-ui-button>
                            @endcan
                            @if(($project->project_type?->value ?? $project->project_type) === 'customer')
                                @php
                                    $companyId = $project->customerProject?->company_id;
                                    $companyName = $companyId ? app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)->displayName($companyId) : null;
                                @endphp
                                <x-ui-button variant="secondary-outline" size="sm" x-data @click="$dispatch('open-modal-customer-project', { projectId: {{ $project->id }} })">
                                    <span class="inline-flex items-center gap-2">
                                        @svg('heroicon-o-user-group','w-4 h-4')
                                        <span class="hidden sm:inline">{{ $companyName ?? 'Kunden' }}</span>
                                    </span>
                                </x-ui-button>
                            @endif
                        </div>
                    </div>
                    <!-- Projekt-Statistiken: Offen -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Offen</h3>
                        <div class="space-y-2">
                            @foreach($statsOpen as $stat)
                                <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                        {{ $stat['count'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Projekt-Statistiken: Erledigt -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Erledigt</h3>
                        <div class="space-y-2">
                            @foreach($statsDone as $stat)
                                <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                        {{ $stat['count'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Debug: Mitgliedstatus & Berechtigungen -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Mitgliedstatus & Berechtigungen</h3>
                        <div class="space-y-2 text-xs p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]">
                            @if($currentUserRole ?? null)
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-[var(--ui-muted)]">Deine Rolle:</span>
                                    <span class="font-medium px-2 py-0.5 rounded bg-[var(--ui-primary-5)] text-[var(--ui-primary)]">
                                        {{ ucfirst($currentUserRole) }}
                                    </span>
                                </div>
                            @elseif($hasAnyTasks ?? false)
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-[var(--ui-muted)]">Mitgliedstatus:</span>
                                    <span class="font-medium px-2 py-0.5 rounded bg-yellow-100 text-yellow-700">
                                        ⚠️ Mit Aufgaben (nicht Mitglied)
                                    </span>
                                </div>
                            @else
                                <div class="flex justify-between items-center py-1">
                                    <span class="text-[var(--ui-muted)]">Mitgliedstatus:</span>
                                    <span class="font-medium px-2 py-0.5 rounded bg-red-100 text-red-700">
                                        ❌ Nicht Mitglied
                                    </span>
                                </div>
                            @endif
                            
                            @if(isset($permissions))
                                <div class="mt-2 pt-2 border-t border-[var(--ui-border)]">
                                    <div class="text-[var(--ui-muted)] mb-1">Berechtigungen:</div>
                                    <div class="space-y-1">
                                        <div class="flex justify-between">
                                            <span>Ansehen:</span>
                                            <span class="{{ $permissions['view'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $permissions['view'] ? '✓' : '✗' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Bearbeiten:</span>
                                            <span class="{{ $permissions['update'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $permissions['update'] ? '✓' : '✗' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Settings:</span>
                                            <span class="{{ $permissions['settings'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $permissions['settings'] ? '✓' : '✗' }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Einladen:</span>
                                            <span class="{{ $permissions['invite'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $permissions['invite'] ? '✓' : '✗' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(isset($allProjectUsers) && $allProjectUsers->count() > 0)
                                <div class="mt-2 pt-2 border-t border-[var(--ui-border)]">
                                    <div class="text-[var(--ui-muted)] mb-1">Projekt-Mitglieder ({{ $allProjectUsers->count() }}):</div>
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @foreach($allProjectUsers as $pu)
                                            <div class="flex justify-between text-xs">
                                                <span class="truncate">{{ $pu->user->name ?? 'Unbekannt' }}</span>
                                                <span class="ml-2 px-1 rounded bg-gray-200 text-gray-700">{{ $pu->role }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Projekt-Details -->
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Details</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Typ:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->project_type?->value ?? $project->project_type ?? 'Unbekannt' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Erstellt:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->created_at->format('d.m.Y') }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Geplant:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->planned_minutes ? number_format($project->planned_minutes / 60, 2, ',', '.') . ' h' : '–' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-[var(--ui-muted)]">Kostenstelle:</span>
                                <span class="text-[var(--ui-secondary)] font-medium">
                                    {{ $project->customer_cost_center ?? '–' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>

        <x-slot name="activity">
            <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
                <div class="p-4 space-y-4">
                    <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                    <div class="space-y-3 text-sm">
                        @foreach(($activities ?? []) as $activity)
                            <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $activity['title'] ?? 'Aktivität' }}</div>
                                <div class="text-[var(--ui-muted)]">{{ $activity['time'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>

        <!-- Board-Container: füllt restliche Breite, Spalten scrollen intern -->
            <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
            {{-- Backlog (nicht sortierbar als Gruppe) --}}
            @php $backlog = $groups->first(fn($g) => ($g->isBacklog ?? false)); @endphp
            @if($backlog)
                <x-ui-kanban-column :title="($backlog->label ?? 'Backlog')" :sortable-id="null" :scrollable="true" :muted="true">
                    <x-slot name="headerActions">
                        <span class="text-xs text-[var(--ui-muted)] font-medium">
                            {{ $backlog->tasks->count() }}
                        </span>
                    </x-slot>
                    @foreach($backlog->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task])
                    @endforeach
                </x-ui-kanban-column>
            @endif

            {{-- Mittlere Spalten (sortierbar) --}}
            @foreach($groups->filter(fn ($g) => !($g->isDoneGroup ?? false) && !($g->isBacklog ?? false)) as $column)
                <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                    <x-slot name="headerActions">
                        @can('update', $project)
                            <button 
                                wire:click="createTask('{{ $column->id }}')" 
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Neue Aufgabe"
                            >
                                @svg('heroicon-o-plus-circle', 'w-4 h-4')
                            </button>
                        @endcan
                        @can('update', $project)
                            <button 
                                @click="$dispatch('open-modal-project-slot-settings', { projectSlotId: {{ $column->id }} })"
                                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                title="Einstellungen"
                            >
                                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                            </button>
                        @endcan
                    </x-slot>

                    @foreach($column->tasks as $task)
                        @include('planner::livewire.task-preview-card', ['task' => $task])
                    @endforeach
                </x-ui-kanban-column>
            @endforeach

            </x-ui-kanban-container>
        {{-- Modals innerhalb des Page-Roots halten (ein Root-Element) --}}
        <livewire:planner.project-settings-modal/>
        <livewire:planner.project-slot-settings-modal/>
        <livewire:planner.customer-project-settings-modal/>
    </x-ui-page>