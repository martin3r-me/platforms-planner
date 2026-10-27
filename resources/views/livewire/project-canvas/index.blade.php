<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-planner::actionbar :project="$project" active="canvas"
            :filters-active="($statusFilter !== '' ? 1 : 0) + ($search !== '' ? 1 : 0)">
            <x-slot:filters>
                <x-planner::filter-group label="Suche">
                    <div class="w-full">
                        <x-nx-input-text wire:model.live.debounce.300ms="search" placeholder="Canvas suchen..." size="sm" />
                    </div>
                </x-planner::filter-group>
                <x-planner::filter-group label="Status">
                    <x-planner::chip :active="$statusFilter === ''" icon="heroicon-o-clipboard-document-list" :count="$stats['total']" wire:click="setStatusFilter('')">Alle</x-planner::chip>
                    @foreach(\Platform\Planner\Models\PlannerProjectCanvas::STATUSES as $st)
                        <x-planner::chip :active="$statusFilter === $st" :icon="\Platform\Planner\Models\PlannerProjectCanvas::STATUS_ICONS[$st]" :count="$stats[$st] ?? 0"
                            wire:click="setStatusFilter('{{ $st }}')">{{ \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$st] }}</x-planner::chip>
                    @endforeach
                </x-planner::filter-group>
            </x-slot:filters>

            @can('update', $project)
                <x-slot:create>
                    <x-planner::menu-item icon="heroicon-o-squares-plus" wire:click="createCanvas">Canvas anlegen</x-planner::menu-item>
                </x-slot:create>
            @endcan
        </x-planner::actionbar>
    </x-slot>

    {{-- Main Content --}}
    <x-planner::body>
        <div class="space-y-4 sm:space-y-6">

            {{-- Stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-4">
                <x-nx-stat label="Gesamt" :value="$stats['total']" hint="Canvases" icon="heroicon-o-clipboard-document-list" />
                @foreach(\Platform\Planner\Models\PlannerProjectCanvas::STATUSES as $st)
                    <x-nx-stat :label="\Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$st]" :value="$stats[$st] ?? 0" :icon="\Platform\Planner\Models\PlannerProjectCanvas::STATUS_ICONS[$st]" />
                @endforeach
            </div>

            {{-- Canvas Table --}}
            @if($canvases->isNotEmpty())
            <x-nx-section title="Canvases" hint="{{ $stats['total'] }} Canvas(es)">
                <x-nx-card flush>
                <x-nx-table compact="true">
                    <x-nx-table-header>
                        <x-nx-table-header-cell compact="true">Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell compact="true">Ampel</x-nx-table-header-cell>
                        <x-nx-table-header-cell compact="true">Status</x-nx-table-header-cell>
                        <x-nx-table-header-cell compact="true">Bloecke</x-nx-table-header-cell>
                        <x-nx-table-header-cell compact="true">Erstellt von</x-nx-table-header-cell>
                        <x-nx-table-header-cell compact="true">Aktualisiert</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($canvases as $canvas)
                        @php $ampel = $canvasStatuses[$canvas->id] ?? null; @endphp
                        <x-nx-table-row compact="true" clickable="true" :href="route('planner.projects.canvas.show', [$project, $canvas])" wire:navigate>
                            <x-nx-table-cell compact="true">
                                <div class="font-medium text-[var(--nx-text)]">{{ $canvas->name }}</div>
                                @if($canvas->description)
                                <div class="text-xs text-[var(--nx-muted)] truncate max-w-xs mt-0.5">{{ Str::limit($canvas->description, 60) }}</div>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell compact="true">
                                @if($ampel)
                                <span class="inline-block w-3 h-3 rounded-full {{ match($ampel['color']) { 'green' => 'bg-[color:var(--nx-success)]', 'yellow' => 'bg-[color:var(--nx-warning)]', default => 'bg-[color:var(--nx-danger)]' } }}"
                                      title="{{ $ampel['score'] }}%"></span>
                                @else
                                <span class="inline-block w-3 h-3 rounded-full bg-[var(--nx-muted)]"></span>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell compact="true">
                                <x-nx-badge :variant="match($canvas->status) { 'completed' => 'success', 'discarded' => 'neutral', default => 'info' }">
                                    {{ \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status }}
                                </x-nx-badge>
                            </x-nx-table-cell>
                            <x-nx-table-cell compact="true">
                                <span class="text-sm">{{ $canvas->blocks_count }}/9</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell compact="true">
                                <span class="text-sm text-[var(--nx-muted)]">{{ $canvas->createdByUser?->name ?? '-' }}</span>
                            </x-nx-table-cell>
                            <x-nx-table-cell compact="true">
                                <span class="text-sm text-[var(--nx-muted)]">{{ $canvas->updated_at?->diffForHumans() }}</span>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>

                <div class="p-4">
                    {{ $canvases->links() }}
                </div>
                </x-nx-card>
            </x-nx-section>
            @else
            {{-- Empty State --}}
            <x-nx-card>
                <div class="p-6 sm:p-12 text-center">
                    @svg('heroicon-o-clipboard-document-list', 'w-16 h-16 text-[var(--nx-muted)] mx-auto mb-4')
                    <h3 class="text-lg font-semibold text-[var(--nx-text)] mb-2">Noch keine Canvases</h3>
                    <p class="text-[var(--nx-muted)] mb-4">Im Canvas steht der aktuelle Stand des Projekts: Ziel, Beteiligte, Nutzen, Risiken.</p>
                    @can('update', $project)
                        <x-nx-button variant="primary" wire:click="createCanvas">Canvas anlegen</x-nx-button>
                    @endcan
                </div>
            </x-nx-card>
            @endif
        </div>
    </x-planner::body>

</x-ui-page>
