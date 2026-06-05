<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => $project->name, 'href' => route('planner.projects.show', $project), 'icon' => 'folder'],
            ['label' => $canvas->name, 'icon' => 'squares-2x2'],
        ]">
            <x-slot name="left">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#f2ca52] text-[#1a1a2e]">
                    Project Canvas
                </span>
            </x-slot>

            {{-- Rechts: Actions --}}
            <button
                wire:click="toggleVisibility"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[13px] font-medium transition-colors {{ $canvas->visibility === 'private' ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50' }}"
            >
                @svg($canvas->visibility === 'private' ? 'heroicon-o-lock-closed' : 'heroicon-o-user-group', 'w-4 h-4')
                <span>{{ $canvas->visibility === 'private' ? 'Privat' : 'Team' }}</span>
            </button>

            <a href="{{ route('planner.projects.canvas.pdf', [$project, $canvas]) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[13px] font-medium text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50 transition-colors">
                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                <span>PDF</span>
            </a>

            {{-- View Mode Toggle --}}
            <div class="flex items-center bg-gray-100 rounded-full p-0.5">
                <button
                    wire:click="$set('viewMode', 'list')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[12px] font-medium transition-all {{ $viewMode === 'list' ? 'bg-white text-[#1a1a2e] shadow-sm' : 'text-gray-400 hover:text-[#1a1a2e]' }}"
                >
                    @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5')
                    <span>Liste</span>
                </button>
                <button
                    wire:click="$set('viewMode', 'workshop')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[12px] font-medium transition-all {{ $viewMode === 'workshop' ? 'bg-white text-[#1a1a2e] shadow-sm' : 'text-gray-400 hover:text-[#1a1a2e]' }}"
                >
                    @svg('heroicon-o-square-3-stack-3d', 'w-3.5 h-3.5')
                    <span>Workshop</span>
                </button>
            </div>

            @if($canvas->public_token)
                <div class="flex items-center gap-1" x-data="{ copied: false }">
                    <button
                        wire:click="togglePublicLink"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[13px] font-medium transition-colors {{ $canvas->is_public ? 'bg-[#f2ca52] text-[#1a1a2e] shadow-sm hover:bg-[#e0b83e]' : 'text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50' }}"
                    >
                        @svg('heroicon-o-globe-alt', 'w-4 h-4')
                        <span>{{ $canvas->is_public ? 'Public' : 'Privat' }}</span>
                    </button>
                    @if($canvas->is_public)
                        <button
                            x-on:click="navigator.clipboard.writeText('{{ $canvas->getPublicUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="p-1.5 rounded-full text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50 transition-colors"
                        >
                            <template x-if="!copied">
                                @svg('heroicon-o-clipboard', 'w-4 h-4')
                            </template>
                            <template x-if="copied">
                                @svg('heroicon-o-check', 'w-4 h-4 text-green-500')
                            </template>
                        </button>
                    @endif
                </div>
            @else
                <button wire:click="createPublicLink"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-[13px] font-medium text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50 transition-colors">
                    @svg('heroicon-o-link', 'w-4 h-4')
                    <span>Teilen</span>
                </button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Main Content --}}
    <x-ui-page-container padding="p-0" spacing="" background="">
        {{-- Meta-Infos --}}
        <div class="px-4 sm:px-6 py-4 border-b border-gray-200/40 bg-white/50">
            <div class="flex flex-wrap items-start gap-6">
                {{-- Status --}}
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Status</span>
                    @php
                        $statusBadge = match($canvas->status) {
                            'open' => 'bg-blue-100 text-blue-700',
                            'completed' => 'bg-green-100 text-green-700',
                            'discarded' => 'bg-gray-100 text-gray-400',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBadge }}">
                        {{ \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status }}
                    </span>
                </div>

                {{-- Ampel (traffic_light strategy) --}}
                @if(($analysisData['strategy'] ?? null) === 'traffic_light')
                <div class="flex items-center gap-2">
                    <span class="inline-block w-4 h-4 rounded-full flex-shrink-0 {{ match($analysisData['color'] ?? 'red') { 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', default => 'bg-red-500' } }}"></span>
                    <span class="text-xs font-semibold text-[#1a1a2e]">{{ $analysisData['score'] ?? 0 }}%</span>
                    <span class="text-xs text-gray-400">
                        {{ match($analysisData['color'] ?? 'red') { 'green' => 'Auf Kurs', 'yellow' => 'Aufmerksamkeit noetig', default => 'Kritisch' } }}
                    </span>
                </div>
                @endif

                {{-- Creator --}}
                <div class="flex items-center gap-1.5 text-xs text-gray-400">
                    @svg('heroicon-o-user', 'w-3.5 h-3.5')
                    {{ $canvas->createdByUser?->name ?? 'Unbekannt' }}
                </div>

                {{-- Date --}}
                <div class="flex items-center gap-1.5 text-xs text-gray-400">
                    @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                    {{ $canvas->created_at?->format('d.m.Y H:i') }}
                </div>

                {{-- Completeness --}}
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-400">Fortschritt</span>
                    @php
                        $barColor = match($analysisData['strategy'] ?? 'basic') {
                            'completeness' => match($analysisData['health'] ?? 'empty') {
                                'good' => 'bg-green-500',
                                'partial' => 'bg-yellow-500',
                                'minimal' => 'bg-orange-500',
                                default => 'bg-gray-300',
                            },
                            'traffic_light' => match($analysisData['color'] ?? 'red') {
                                'green' => 'bg-green-500',
                                'yellow' => 'bg-yellow-500',
                                default => 'bg-red-500',
                            },
                            default => 'bg-[#f2ca52]',
                        };
                    @endphp
                    <div class="w-24 h-2 rounded-full bg-gray-100">
                        <div class="h-2 rounded-full transition-all {{ $barColor }}"
                             style="width: {{ $analysisData['completeness_percent'] ?? 0 }}%"></div>
                    </div>
                    <span class="text-xs font-semibold text-[#1a1a2e]">{{ $analysisData['completeness_percent'] ?? 0 }}%</span>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span>{{ $analysisData['filled_blocks'] ?? 0 }}/{{ $analysisData['total_blocks'] ?? 0 }} Bloecke</span>
                    <span>{{ $analysisData['total_entries'] ?? 0 }} Eintraege</span>
                    @if(($analysisData['strategy'] ?? null) === 'completeness')
                        @php
                            $healthBadge = match($analysisData['health'] ?? 'empty') {
                                'good' => 'bg-green-100 text-green-700',
                                'partial' => 'bg-amber-100 text-amber-700',
                                default => 'bg-red-100 text-red-700',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $healthBadge }}">
                            {{ ucfirst($analysisData['health'] ?? 'empty') }}
                        </span>
                    @endif
                    @if(($analysisData['strategy'] ?? null) === 'traffic_light')
                        <span>{{ $analysisData['risk_count'] ?? 0 }} Risiken</span>
                        @if(($analysisData['overdue_milestones'] ?? 0) > 0)
                            <span class="text-red-600 font-medium">{{ $analysisData['overdue_milestones'] }} Ueberfaellig</span>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Description --}}
            @if($canvas->description)
            <p class="mt-2 text-xs text-gray-400 leading-relaxed">{{ $canvas->description }}</p>
            @endif

            {{-- Warnings (traffic_light) --}}
            @if(!empty($analysisData['warnings'] ?? []))
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($analysisData['warnings'] as $warning)
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 text-yellow-600 flex-shrink-0')
                    <span class="text-[11px] text-[#1a1a2e]">{{ $warning }}</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Recommendations (completeness) --}}
            @if(($analysisData['strategy'] ?? null) === 'completeness' && !empty($analysisData['missing_blocks'] ?? []))
            <div class="mt-3">
                <details class="group">
                    <summary class="text-xs font-medium text-gray-400 cursor-pointer hover:text-[#1a1a2e]">
                        {{ count($analysisData['missing_blocks']) }} fehlende Bloecke anzeigen
                    </summary>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($analysisData['missing_blocks'] as $missing)
                        <div class="px-2.5 py-1.5 rounded-xl bg-gray-50 border border-gray-200/40">
                            <div class="text-xs font-semibold text-[#1a1a2e]">{{ $missing['label'] }}</div>
                            @foreach($missing['guiding_questions'] ?? [] as $question)
                            <div class="text-[11px] text-gray-400 flex items-start gap-1 mt-0.5">
                                @svg('heroicon-o-question-mark-circle', 'w-3 h-3 mt-0.5 flex-shrink-0')
                                {{ $question }}
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                </details>
            </div>
            @endif

            @if(($analysisData['strategy'] ?? null) === 'completeness' && !empty($analysisData['recommendations'] ?? []))
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($analysisData['recommendations'] as $rec)
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-yellow-500/10 border border-yellow-500/20">
                    @svg('heroicon-o-light-bulb', 'w-3.5 h-3.5 text-yellow-600 flex-shrink-0')
                    <span class="text-[11px] text-[#1a1a2e]">{{ $rec }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        @if($viewMode === 'list')
        {{-- ═══ LIST VIEW ═══ --}}
        <div wire:key="view-list" x-data="blockNav()" x-init="init()">
            {{-- Block Navigation --}}
            <div class="sticky top-0 z-20 border-b border-gray-200/40 bg-white/95 backdrop-blur-sm">
                <div class="px-4 sm:px-6 overflow-x-auto">
                    <div class="flex items-center gap-1 py-2">
                        <span class="shrink-0 text-[11px] font-bold text-[#f2ca52] uppercase tracking-wider mr-2">Project Canvas</span>
                        <span class="shrink-0 w-px h-4 bg-gray-200/40 mr-1"></span>
                        @foreach($blockDefs as $def)
                            @php
                                $blockKey = $def['key'];
                                $label = $def['label'] ?? ucfirst(str_replace('_', ' ', $blockKey));
                            @endphp
                            <button
                                x-on:click="scrollTo('block-{{ $blockKey }}')"
                                :class="activeBlock === 'block-{{ $blockKey }}' ? 'bg-[#f2ca52] text-[#1a1a2e] shadow-sm' : 'text-gray-400 hover:text-[#1a1a2e] hover:bg-yellow-50'"
                                class="shrink-0 px-3 py-1.5 rounded-full text-[11px] font-medium transition-all whitespace-nowrap"
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Blocks --}}
            <div class="p-4 sm:p-6 space-y-6">
                @foreach($blockDefs as $def)
                    <div id="block-{{ $def['key'] }}" class="scroll-mt-14" data-block>
                        @include('planner::livewire.project-canvas._block', ['blockKey' => $def['key'], 'blocks' => $canvasData['blocks'], 'blockDefs' => $blockDefs])
                    </div>
                @endforeach
                <div class="h-[60vh]"></div>
            </div>
        </div>

        <script>
        function blockNav() {
            return {
                activeBlock: '',
                observer: null,
                init() {
                    this.$nextTick(() => {
                        const scrollArea = this.$el.closest('.overflow-y-auto');
                        const blocks = this.$el.querySelectorAll('[data-block]');
                        if (!blocks.length) return;
                        this.activeBlock = blocks[0]?.id || '';
                        this.observer = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    this.activeBlock = entry.target.id;
                                }
                            });
                        }, { root: scrollArea, rootMargin: '-10% 0px -70% 0px', threshold: 0 });
                        blocks.forEach(block => this.observer.observe(block));
                    });
                },
                scrollTo(id) {
                    const el = document.getElementById(id);
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
        </script>

        @else
        {{-- ═══ WORKSHOP VIEW (Infinite Canvas — JS-owned DOM) ═══ --}}
        @php
            $gridCols = $layout['columns'] ?? 3;
            $gridRows = $layout['rows'] ?? 3;
            $areasRaw = $layout['areas'] ?? '';
            $areaMap = is_array($layout['area_map'] ?? null) ? $layout['area_map'] : [];
            $blocksById = $canvas->blocks->keyBy('block_type');
            $ws = $canvas->workshop_settings ?? [];
            $gridW = (int) ($ws['gridWidth'] ?? max(1200, $gridCols * 280));
            $gridH = (int) ($ws['gridHeight'] ?? max(800, $gridRows * 280));
            $boardW = 5000;
            $boardH = 3000;
            $gridLeft = intval(($boardW - $gridW) / 2);
            $gridTop = intval(($boardH - $gridH) / 2);

            // Normalize area placements
            $blockPlacement = [];
            if (is_array($areasRaw) && !empty($areasRaw) && isset($areasRaw[0]['block'])) {
                foreach ($areasRaw as $area) {
                    $blockPlacement[$area['block']] = [
                        'col' => $area['col'] ?? 1,
                        'row' => $area['row'] ?? 1,
                        'colspan' => $area['colspan'] ?? 1,
                        'rowspan' => $area['rowspan'] ?? 1,
                    ];
                }
            } elseif (is_string($areasRaw) && !empty($areasRaw) && !empty($areaMap)) {
                $reverseMap = array_flip($areaMap);
                $rows = explode('/', $areasRaw);
                $grid = [];
                foreach ($rows as $ri => $row) {
                    $tokens = preg_split('/\s+/', trim($row), -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($tokens as $ci => $token) {
                        $blockKey = $reverseMap[$token] ?? $token;
                        $grid[$ri][$ci] = $blockKey;
                    }
                }
                $seen = [];
                foreach ($grid as $ri => $cols) {
                    foreach ($cols as $ci => $blockKey) {
                        if (isset($seen[$blockKey])) continue;
                        $seen[$blockKey] = true;
                        $colspan = 1;
                        while (($ci + $colspan) < count($cols) && $cols[$ci + $colspan] === $blockKey) $colspan++;
                        $rowspan = 1;
                        while (($ri + $rowspan) < count($grid) && ($grid[$ri + $rowspan][$ci] ?? null) === $blockKey) $rowspan++;
                        $blockPlacement[$blockKey] = [
                            'col' => $ci + 1,
                            'row' => $ri + 1,
                            'colspan' => $colspan,
                            'rowspan' => $rowspan,
                        ];
                    }
                }
            }
        @endphp

        <div wire:key="view-workshop"
             wire:ignore
             x-data="workshopBoard({
                notes: {{ Js::from($workshopNotes) }},
                canvasBlocks: {{ Js::from(collect($blockDefs)->map(fn($d) => ['key' => $d['key'], 'label' => $d['label'] ?? $d['key'], 'id' => $blocksById[$d['key']]?->id ?? null])->values()) }},
                gridLayout: {{ Js::from($layout) }}
             })"
             class="relative overflow-hidden"
             :class="isFullscreen ? 'workshop-fullscreen' : 'h-[calc(100vh-220px)]'"
             style="background: #eef0f4;"
        >
            {{-- Board Presence (visible in fullscreen) --}}
            <div class="workshop-presence">
                @livewire('core.page-presence', key('workshop-presence'))
            </div>

            {{-- Zoom Controls --}}
            <div class="workshop-zoom-controls">
                <button x-on:click="zoomIn()" title="Zoom In">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                    </svg>
                </button>
                <div class="zoom-level" x-text="Math.round(scale * 100) + '%'"></div>
                <button x-on:click="zoomOut()" title="Zoom Out">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button x-on:click="resetZoom()" title="Reset Zoom" class="text-[10px] font-bold">1:1</button>
                <button x-on:click="fitToScreen()" title="An Bildschirm anpassen">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v2.5a.75.75 0 001.5 0v-2.5a.75.75 0 01.75-.75h2.5a.75.75 0 000-1.5h-2.5zM13.25 2a.75.75 0 000 1.5h2.5a.75.75 0 01.75.75v2.5a.75.75 0 001.5 0v-2.5A2.25 2.25 0 0015.75 2h-2.5zM3.5 13.25a.75.75 0 00-1.5 0v2.5A2.25 2.25 0 004.25 18h2.5a.75.75 0 000-1.5h-2.5a.75.75 0 01-.75-.75v-2.5zM18 13.25a.75.75 0 00-1.5 0v2.5a.75.75 0 01-.75.75h-2.5a.75.75 0 000 1.5h2.5A2.25 2.25 0 0018 15.75v-2.5z" clip-rule="evenodd" />
                    </svg>
                </button>
                <button x-on:click="toggleFullscreen()" title="Vollbild">
                    <template x-if="!isFullscreen">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M3.28 2.22a.75.75 0 00-1.06 1.06L5.44 6.5H2.75a.75.75 0 000 1.5h4.5A.75.75 0 008 7.25v-4.5a.75.75 0 00-1.5 0v2.69L3.28 2.22zM16.72 2.22a.75.75 0 010 1.06L13.56 6.5h2.69a.75.75 0 010 1.5h-4.5A.75.75 0 0111 7.25v-4.5a.75.75 0 011.5 0v2.69l3.22-3.22a.75.75 0 011.06 0zM3.28 17.78a.75.75 0 001.06 0L7.56 14.5h-2.69a.75.75 0 010-1.5h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-2.69l-3.22 3.22a.75.75 0 01-1.06-1.06zM16.72 17.78a.75.75 0 01-1.06 0L12.44 14.5h2.69a.75.75 0 000-1.5h-4.5a.75.75 0 00-.75.75v4.5a.75.75 0 001.5 0v-2.69l3.22 3.22a.75.75 0 001.06-1.06z" />
                        </svg>
                    </template>
                    <template x-if="isFullscreen">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M3.28 2.22a.75.75 0 00-1.06 1.06L5.44 6.5H2.75a.75.75 0 000 1.5h4.5A.75.75 0 008 7.25v-4.5a.75.75 0 00-1.5 0v2.69L3.28 2.22z" />
                        </svg>
                    </template>
                </button>
            </div>

            {{-- Element Toolbar --}}
            <div class="workshop-toolbar">
                <button class="workshop-toolbar-btn" x-on:click="addElement('note')" title="Sticky Note">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V4z"/></svg>
                    <span>Notiz</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('text')" title="Textlabel">
                    <span style="font-weight:800;font-size:14px;line-height:1;">T</span>
                    <span>Text</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('section')" title="Section / Frame">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4"><rect x="3" y="3" width="14" height="14" rx="2" stroke-dasharray="3 2"/></svg>
                    <span>Section</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('shape')" title="Form">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><circle cx="10" cy="10" r="7"/></svg>
                    <span>Form</span>
                </button>
                <button class="workshop-toolbar-btn"
                        x-on:click="startConnectorMode()"
                        :class="{ 'active': _connectorMode }"
                        title="Verbindung">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M2 10a.75.75 0 01.75-.75h12.69l-4.72-4.72a.75.75 0 011.06-1.06l6 6a.75.75 0 010 1.06l-6 6a.75.75 0 11-1.06-1.06l4.72-4.72H2.75A.75.75 0 012 10z" clip-rule="evenodd"/></svg>
                    <span>Pfeil</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('kanban')" title="Kanban Board">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M2 4.5A2.5 2.5 0 014.5 2h2A2.5 2.5 0 019 4.5v11A2.5 2.5 0 016.5 18h-2A2.5 2.5 0 012 15.5v-11zM11 4.5A2.5 2.5 0 0113.5 2h2A2.5 2.5 0 0118 4.5v6a2.5 2.5 0 01-2.5 2.5h-2A2.5 2.5 0 0111 10.5v-6z"/></svg>
                    <span>Kanban</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('image')" title="Bild">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M1 5.25A2.25 2.25 0 013.25 3h13.5A2.25 2.25 0 0119 5.25v9.5A2.25 2.25 0 0116.75 17H3.25A2.25 2.25 0 011 14.75v-9.5zm1.5 5.81V14.75c0 .414.336.75.75.75h13.5a.75.75 0 00.75-.75v-2.06l-2.22-2.22a.75.75 0 00-1.06 0L9.06 15.56l-3.28-3.28a.75.75 0 00-1.06 0l-2.22 2.22zM5.5 7a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd"/></svg>
                    <span>Bild</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('image_grid')" title="Bilder-Grid">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v2.5A2.25 2.25 0 004.25 9h2.5A2.25 2.25 0 009 6.75v-2.5A2.25 2.25 0 006.75 2h-2.5zm0 9A2.25 2.25 0 002 13.25v2.5A2.25 2.25 0 004.25 18h2.5A2.25 2.25 0 009 15.75v-2.5A2.25 2.25 0 006.75 11h-2.5zm9-9A2.25 2.25 0 0011 4.25v2.5A2.25 2.25 0 0013.25 9h2.5A2.25 2.25 0 0018 6.75v-2.5A2.25 2.25 0 0015.75 2h-2.5zm0 9A2.25 2.25 0 0011 13.25v2.5A2.25 2.25 0 0013.25 18h2.5A2.25 2.25 0 0018 15.75v-2.5A2.25 2.25 0 0015.75 11h-2.5z" clip-rule="evenodd"/></svg>
                    <span>Grid</span>
                </button>
                <button class="workshop-toolbar-btn" x-on:click="addElement('video')" title="Video">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                    <span>Video</span>
                </button>
            </div>

            {{-- Board --}}
            <div x-ref="board" class="workshop-board" style="width: {{ $boardW }}px; height: {{ $boardH }}px;">
                {{-- Canvas Grid (read-only, Blade-rendered, static) --}}
                <div class="workshop-canvas-background" style="
                    position: absolute;
                    top: {{ $gridTop }}px;
                    left: {{ $gridLeft }}px;
                    width: {{ $gridW }}px;
                    min-height: {{ $gridH }}px;
                    display: grid;
                    grid-template-columns: repeat({{ $gridCols }}, 1fr);
                    grid-template-rows: repeat({{ $gridRows }}, minmax(180px, auto));
                    gap: 1.5px;
                    background: #2d2d2d;
                    border: 1.5px solid #2d2d2d;
                    border-radius: 4px;
                    overflow: hidden;
                ">
                    @foreach($blockDefs as $def)
                        @include('planner::livewire.project-canvas._workshop-grid-block', [
                            'blockKey' => $def['key'],
                            'blockDef' => $def,
                            'block' => $blocksById[$def['key']] ?? null,
                            'canvasData' => $canvasData,
                            'placement' => $blockPlacement[$def['key']] ?? null,
                        ])
                    @endforeach
                </div>
                {{-- Notes are rendered by JS from the notes data --}}
            </div>
        </div>
        @endif
    </x-ui-page-container>

    {{-- Left Sidebar: Kommentare --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Kommentare ({{ $allComments->count() }})" width="w-80" :defaultOpen="true" storeKey="sidebarOpen">
            <div class="p-4 space-y-4">
                {{-- Block Filter Chips --}}
                <div class="overflow-x-auto -mx-4 px-4">
                    <div class="flex items-center gap-1.5 flex-nowrap">
                        <button
                            wire:click="filterByBlock(null)"
                            class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-medium transition-colors whitespace-nowrap {{ !$filterBlockId ? 'bg-[#f2ca52] text-[#1a1a2e]' : 'bg-gray-100 text-gray-400 hover:text-[#1a1a2e]' }}"
                        >
                            Alle
                        </button>
                        @foreach($canvas->blocks as $block)
                            @php $blockCount = $allComments->where('building_block_id', $block->id)->count(); @endphp
                            @if($blockCount > 0)
                            <button
                                wire:click="filterByBlock({{ $block->id }})"
                                class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-medium transition-colors whitespace-nowrap {{ $filterBlockId === $block->id ? 'bg-[#f2ca52] text-[#1a1a2e]' : 'bg-gray-100 text-gray-400 hover:text-[#1a1a2e]' }}"
                            >
                                {{ $block->label }} ({{ $blockCount }})
                            </button>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Comment Form --}}
                <form wire:submit="addComment" class="space-y-2">
                    @if($replyToId)
                        @php $replyTarget = $comments->firstWhere('id', $replyToId); @endphp
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-xl bg-[#f2ca52]/10 border border-[#f2ca52]/30">
                            @svg('heroicon-o-arrow-uturn-left', 'w-3 h-3 text-[#f2ca52] shrink-0')
                            <span class="text-[10px] text-[#1a1a2e] truncate grow">
                                Antwort auf: {{ Str::limit($replyTarget?->content ?? '', 50) }}
                            </span>
                            <button type="button" wire:click="cancelReply" class="shrink-0 text-gray-400 hover:text-[#1a1a2e]">
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                            </button>
                        </div>
                    @elseif($commentBlockId)
                        @php $selectedBlock = $canvas->blocks->firstWhere('id', $commentBlockId); @endphp
                        <div class="flex items-center gap-2 px-2 py-1.5 rounded-xl bg-gray-50 border border-gray-200">
                            @svg('heroicon-o-cube', 'w-3 h-3 text-gray-400 shrink-0')
                            <span class="text-[10px] text-[#1a1a2e] grow">{{ $selectedBlock?->label ?? 'Block' }}</span>
                            <button type="button" wire:click="$set('commentBlockId', null)" class="shrink-0 text-gray-400 hover:text-[#1a1a2e]">
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                            </button>
                        </div>
                    @else
                        @php
                            $blockOptions = $canvas->blocks->mapWithKeys(fn($b) => [$b->id => $b->label])->toArray();
                        @endphp
                        <select
                            wire:model="commentBlockId"
                            class="w-full px-3 py-2 text-[13px] rounded-xl border border-gray-200 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#f2ca52]/30 focus:border-[#f2ca52] transition-colors appearance-none pr-10 bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg+xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22+viewBox%3D%220+0+20+20%22+fill%3D%22%236b7280%22%3E%3Cpath+fill-rule%3D%22evenodd%22+d%3D%22M5.23+7.21a.75.75+0+011.06.02L10+11.168l3.71-3.938a.75.75+0+111.08+1.04l-4.25+4.5a.75.75+0+01-1.08+0l-4.25-4.5a.75.75+0+01.02-1.06z%22+clip-rule%3D%22evenodd%22%2F%3E%3C%2Fsvg%3E')] bg-[length:20px_20px] bg-[position:right_8px_center] bg-no-repeat"
                        >
                            <option value="">Canvas-weiter Kommentar</option>
                            @foreach($blockOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif

                    <div class="flex gap-2">
                        <textarea
                            wire:model="commentContent"
                            rows="2"
                            placeholder="{{ $replyToId ? 'Antwort schreiben...' : 'Kommentar schreiben...' }}"
                            class="grow rounded-xl border border-gray-200 bg-white text-xs text-[#1a1a2e] p-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-[#f2ca52]/30 focus:border-[#f2ca52] transition-colors"
                        ></textarea>
                        <button
                            type="submit"
                            class="shrink-0 self-end px-3 py-2 rounded-full bg-[#f2ca52] text-[#1a1a2e] text-xs font-bold hover:bg-[#e0b83e] transition-colors disabled:opacity-50 shadow-sm"
                            wire:loading.attr="disabled"
                        >
                            @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                        </button>
                    </div>
                    @error('commentContent')
                        <p class="text-[10px] text-red-500">{{ $message }}</p>
                    @enderror
                </form>

                {{-- Comments List --}}
                <div class="space-y-3">
                    @forelse($comments as $comment)
                        <div class="space-y-2">
                            {{-- Root Comment --}}
                            <div class="rounded-xl border border-gray-200 bg-white p-3 hover:shadow-sm transition-shadow">
                                <div class="flex items-center gap-2 mb-1.5">
                                    @if($comment->building_block_id)
                                        <span class="flex items-center gap-1 text-[9px] font-medium text-[#f2ca52] bg-[#f2ca52]/10 rounded-full px-1.5 py-0.5">
                                            @svg('heroicon-o-cube', 'w-2.5 h-2.5')
                                            {{ $comment->block?->label ?? 'Block' }}
                                        </span>
                                    @else
                                        <span class="text-[9px] font-medium text-gray-400 bg-gray-100 rounded-full px-1.5 py-0.5">Canvas-weit</span>
                                    @endif
                                    <span class="text-[10px] text-gray-300 ml-auto">{{ $comment->created_at->format('d.m. H:i') }}</span>
                                </div>
                                <p class="text-xs text-[#1a1a2e] leading-relaxed whitespace-pre-line">{{ $comment->content }}</p>
                                <div class="mt-2 flex items-center gap-2">
                                    <button
                                        wire:click="setReplyTo({{ $comment->id }})"
                                        class="flex items-center gap-1 text-[10px] text-gray-400 hover:text-[#f2ca52] transition-colors"
                                    >
                                        @svg('heroicon-o-arrow-uturn-left', 'w-3 h-3')
                                        Antworten
                                    </button>
                                    @if($comment->replies->count() > 0)
                                        <span class="text-[10px] text-gray-300">
                                            {{ $comment->replies->count() }} {{ $comment->replies->count() === 1 ? 'Antwort' : 'Antworten' }}
                                        </span>
                                    @endif
                                    <button
                                        wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="Kommentar und alle Antworten loeschen?"
                                        class="flex items-center gap-1 text-[10px] text-gray-400 hover:text-red-500 transition-colors ml-auto"
                                    >
                                        @svg('heroicon-o-trash', 'w-3 h-3')
                                    </button>
                                </div>
                            </div>

                            {{-- Replies --}}
                            @if($comment->replies->count() > 0)
                                <div class="ml-4 space-y-2 border-l-2 border-gray-100 pl-3">
                                    @foreach($comment->replies as $reply)
                                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-2.5 group/reply">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-[10px] text-gray-300">{{ $reply->created_at->format('d.m. H:i') }}</span>
                                                <button
                                                    wire:click="deleteComment({{ $reply->id }})"
                                                    wire:confirm="Antwort loeschen?"
                                                    class="ml-auto opacity-0 group-hover/reply:opacity-100 text-gray-400 hover:text-red-500 transition-all"
                                                >
                                                    @svg('heroicon-o-trash', 'w-3 h-3')
                                                </button>
                                            </div>
                                            <p class="text-[11px] text-[#1a1a2e] leading-relaxed whitespace-pre-line">{{ $reply->content }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-8">
                            @svg('heroicon-o-chat-bubble-left-right', 'w-8 h-8 text-gray-200 mx-auto mb-3')
                            <p class="text-xs text-gray-400">
                                {{ $filterBlockId ? 'Keine Kommentare fuer diesen Block.' : 'Noch keine Kommentare.' }}
                            </p>
                            @if($filterBlockId)
                                <button wire:click="filterByBlock(null)" class="mt-2 text-[10px] text-[#f2ca52] hover:text-[#e0b83e]">
                                    Alle Kommentare anzeigen
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Right Sidebar: Aktivitaeten --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6">
                <h3 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-4">Letzte Aktivitaeten</h3>
                <div class="space-y-3">
                    @forelse(($activities ?? []) as $activity)
                        <div class="p-3 rounded-xl border border-gray-200 bg-white hover:shadow-sm transition-shadow">
                            <div class="flex items-start justify-between gap-2 mb-1">
                                <div class="flex-1 min-w-0">
                                    <div class="text-[13px] font-medium text-[#1a1a2e] leading-snug">
                                        {{ $activity['title'] ?? 'Aktivitaet' }}
                                    </div>
                                </div>
                                @if(($activity['type'] ?? null) === 'system')
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 border border-gray-200 text-xs text-gray-400">
                                            @svg('heroicon-o-cog', 'w-3 h-3')
                                            System
                                        </span>
                                    </div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-400">
                                @svg('heroicon-o-clock', 'w-3 h-3')
                                <span>{{ $activity['time'] ?? '' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-yellow-50 mb-3">
                                @svg('heroicon-o-clock', 'w-6 h-6 text-[#f2ca52]')
                            </div>
                            <p class="text-[13px] text-gray-400">Noch keine Aktivitaeten</p>
                            <p class="text-xs text-gray-400 mt-1">Aenderungen werden hier angezeigt</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
