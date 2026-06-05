<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => $project->name, 'href' => route('planner.projects.show', $project), 'icon' => 'folder'],
            ['label' => 'Project Canvas', 'href' => route('planner.projects.canvas.index', $project), 'icon' => 'clipboard-document-list'],
            ['label' => $canvas->name],
        ]">
            <x-slot name="left">
                <a href="{{ route('planner.projects.canvas.pdf', [$project, $canvas]) }}" target="_blank">
                    <x-ui-button variant="ghost" size="sm">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                        <span>PDF Export</span>
                    </x-ui-button>
                </a>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Main Content --}}
    <x-ui-page-container>
        <div class="space-y-4">
            {{-- 3x3 Grid --}}
            <div class="grid grid-cols-3 gap-3">
                @include('planner::livewire.project-canvas._block', ['blockType' => 'project_goal', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'scope', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'stakeholders', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])

                @include('planner::livewire.project-canvas._block', ['blockType' => 'risks', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'milestones', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'resources', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])

                @include('planner::livewire.project-canvas._block', ['blockType' => 'budget', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'communication', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
                @include('planner::livewire.project-canvas._block', ['blockType' => 'governance', 'blocks' => $canvasData['blocks'], 'blockTypes' => $blockTypes])
            </div>
        </div>
    </x-ui-page-container>

    {{-- Left Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Canvas Info" width="w-72" :defaultOpen="true">
            <div class="p-5 space-y-5">
                {{-- Status --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Status</h3>
                    <x-ui-badge :variant="match($canvas->status) { 'active' => 'success', 'archived' => 'secondary', default => 'warning' }">
                        {{ ucfirst($canvas->status) }}
                    </x-ui-badge>
                </div>

                {{-- Ampel --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Ampel</h3>
                    <div class="d-flex items-center gap-3 p-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        <span class="inline-block w-6 h-6 rounded-full flex-shrink-0 {{ match($statusData['color']) { 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', default => 'bg-red-500' } }}"></span>
                        <div>
                            <div class="text-sm font-bold text-[var(--ui-secondary)]">{{ $statusData['score'] }}%</div>
                            <div class="text-[11px] text-[var(--ui-muted)]">
                                {{ match($statusData['color']) { 'green' => 'Auf Kurs', 'yellow' => 'Aufmerksamkeit noetig', default => 'Kritisch' } }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Creator & Date --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Details</h3>
                    <div class="space-y-2 text-xs text-[var(--ui-muted)]">
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-3.5 h-3.5')
                            {{ $canvas->createdByUser?->name ?? 'Unbekannt' }}
                        </div>
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                            {{ $canvas->created_at?->format('d.m.Y H:i') }}
                        </div>
                    </div>
                </div>

                {{-- Description --}}
                @if($canvas->description)
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Beschreibung</h3>
                    <p class="text-xs text-[var(--ui-muted)] leading-relaxed">{{ $canvas->description }}</p>
                </div>
                @endif

                {{-- Completeness --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Fortschritt</h3>
                    <div class="space-y-2">
                        {{-- Progress Bar --}}
                        <div>
                            <div class="d-flex items-center justify-between text-xs mb-1">
                                <span class="text-[var(--ui-muted)]">Vollstaendigkeit</span>
                                <span class="font-semibold text-[var(--ui-secondary)]">{{ $statusData['completeness_percent'] }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-[var(--ui-muted-5)]">
                                <div class="h-2 rounded-full transition-all {{ match($statusData['color']) { 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', default => 'bg-red-500' } }}"
                                     style="width: {{ $statusData['completeness_percent'] }}%"></div>
                            </div>
                        </div>

                        {{-- Stats --}}
                        <div class="space-y-1.5">
                            <div class="d-flex items-center justify-between p-2 bg-[var(--ui-muted-5)] rounded-md border border-[var(--ui-border)]/40">
                                <span class="text-[11px] text-[var(--ui-muted)]">Bloecke</span>
                                <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $statusData['filled_blocks'] }}/{{ $statusData['total_blocks'] }}</span>
                            </div>
                            <div class="d-flex items-center justify-between p-2 bg-[var(--ui-muted-5)] rounded-md border border-[var(--ui-border)]/40">
                                <span class="text-[11px] text-[var(--ui-muted)]">Eintraege</span>
                                <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $statusData['total_entries'] }}</span>
                            </div>
                            <div class="d-flex items-center justify-between p-2 bg-[var(--ui-muted-5)] rounded-md border border-[var(--ui-border)]/40">
                                <span class="text-[11px] text-[var(--ui-muted)]">Risiken</span>
                                <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $statusData['risk_count'] }}</span>
                            </div>
                            @if($statusData['overdue_milestones'] > 0)
                            <div class="d-flex items-center justify-between p-2 bg-red-500/10 rounded-md border border-red-500/20">
                                <span class="text-[11px] text-red-600">Ueberfaellig</span>
                                <span class="text-xs font-bold text-red-600">{{ $statusData['overdue_milestones'] }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Warnings --}}
                @if(!empty($statusData['warnings']))
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Warnungen</h3>
                    <div class="space-y-1.5">
                        @foreach($statusData['warnings'] as $warning)
                        <div class="d-flex items-start gap-2 p-2 rounded-md bg-yellow-500/10 border border-yellow-500/20">
                            @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 text-yellow-600 mt-0.5 flex-shrink-0')
                            <span class="text-[11px] text-[var(--ui-secondary)]">{{ $warning }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
