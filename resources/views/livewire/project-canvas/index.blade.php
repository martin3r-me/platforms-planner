<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => $project->name, 'href' => route('planner.projects.show', $project), 'icon' => 'folder'],
            ['label' => 'Project Canvas', 'icon' => 'clipboard-document-list'],
        ]" />
    </x-slot>

    {{-- Main Content --}}
    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-nx-stat label="Gesamt" :value="$stats['total']" hint="Canvases" icon="clipboard-document-list" />
                <x-nx-stat label="Entwurf" :value="$stats['draft']" hint="Draft" icon="pencil-square" />
                <x-nx-stat label="Aktiv" :value="$stats['active']" hint="In Bearbeitung" icon="check-circle" />
                <x-nx-stat label="Archiviert" :value="$stats['archived']" hint="Abgeschlossen" icon="archive-box" />
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
                                <x-nx-badge :variant="match($canvas->status) { 'active' => 'success', 'archived' => 'neutral', default => 'warning' }">
                                    {{ ucfirst($canvas->status) }}
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
                <div class="p-12 text-center">
                    @svg('heroicon-o-clipboard-document-list', 'w-16 h-16 text-[var(--nx-muted)] mx-auto mb-4')
                    <h3 class="text-lg font-semibold text-[var(--nx-text)] mb-2">Noch keine Canvases</h3>
                    <p class="text-[var(--nx-muted)]">Erstelle dein erstes Project Canvas per Chat.</p>
                </div>
            </x-nx-card>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Left Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-72" :defaultOpen="true">
            <div class="p-5 space-y-5">
                {{-- Search --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-nx-input-text
                        wire:model.live.debounce.300ms="search"
                        placeholder="Canvas suchen..."
                        size="sm"
                    />
                </div>

                {{-- Status Filter --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--nx-text)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-1">
                        <button wire:click="setStatusFilter('')"
                            class="d-flex items-center justify-between w-full p-2 rounded-md text-xs transition-colors {{ $statusFilter === '' ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)] hover:text-[var(--nx-text)]' }}">
                            <span class="d-flex items-center gap-2">
                                @svg('heroicon-o-clipboard-document-list', 'w-3.5 h-3.5')
                                Alle
                            </span>
                            <span>{{ $stats['total'] }}</span>
                        </button>
                        <button wire:click="setStatusFilter('draft')"
                            class="d-flex items-center justify-between w-full p-2 rounded-md text-xs transition-colors {{ $statusFilter === 'draft' ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)] hover:text-[var(--nx-text)]' }}">
                            <span class="d-flex items-center gap-2">
                                @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                                Entwurf
                            </span>
                            <span>{{ $stats['draft'] }}</span>
                        </button>
                        <button wire:click="setStatusFilter('active')"
                            class="d-flex items-center justify-between w-full p-2 rounded-md text-xs transition-colors {{ $statusFilter === 'active' ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)] hover:text-[var(--nx-text)]' }}">
                            <span class="d-flex items-center gap-2">
                                @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                Aktiv
                            </span>
                            <span>{{ $stats['active'] }}</span>
                        </button>
                        <button wire:click="setStatusFilter('archived')"
                            class="d-flex items-center justify-between w-full p-2 rounded-md text-xs transition-colors {{ $statusFilter === 'archived' ? 'bg-[var(--nx-accent)]/10 text-[var(--nx-accent)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)] hover:text-[var(--nx-text)]' }}">
                            <span class="d-flex items-center gap-2">
                                @svg('heroicon-o-archive-box', 'w-3.5 h-3.5')
                                Archiviert
                            </span>
                            <span>{{ $stats['archived'] }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
