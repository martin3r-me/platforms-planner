@php
    $statusBadge = match($canvas->status) {
        'open' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-green-100 text-green-700',
        'discarded' => 'bg-gray-100 text-gray-500',
        default => 'bg-gray-100 text-gray-600',
    };
    $statusLabel = \Platform\Planner\Models\PlannerProjectCanvas::STATUS_LABELS[$canvas->status] ?? $canvas->status;
@endphp

<div class="min-h-screen flex flex-col bg-[var(--ui-bg,#f8fafc)]">
    {{-- Header --}}
    <header class="flex-shrink-0 border-b border-[var(--ui-border,#e2e8f0)] bg-white">
        <div class="px-4 sm:px-6 py-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs text-gray-400">
                        <a href="{{ route('planner.public.show', $token) }}" class="hover:text-[#1a1a2e] inline-flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-3.5 h-3.5')
                            <span class="truncate max-w-[200px]">{{ $project->name }}</span>
                        </a>
                        <span class="text-gray-300">/</span>
                        <span class="inline-flex items-center gap-1 text-[#1a1a2e] font-medium">
                            @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5')
                            {{ $canvas->name }}
                        </span>
                    </div>
                    <h1 class="mt-0.5 text-lg font-semibold text-[#1a1a2e] truncate">{{ $canvas->name }}</h1>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#f2ca52] text-[#1a1a2e]">
                        Project Canvas
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusBadge }}">
                        {{ $statusLabel }}
                    </span>

                    {{-- View Mode Toggle --}}
                    <div class="flex items-center bg-gray-100 rounded-full p-0.5">
                        <button
                            wire:click="setViewMode('list')"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[12px] font-medium transition-all {{ $viewMode === 'list' ? 'bg-white text-[#1a1a2e] shadow-sm' : 'text-gray-400 hover:text-[#1a1a2e]' }}"
                        >
                            @svg('heroicon-o-list-bullet', 'w-3.5 h-3.5')
                            <span>Liste</span>
                        </button>
                        <button
                            wire:click="setViewMode('workshop')"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-[12px] font-medium transition-all {{ $viewMode === 'workshop' ? 'bg-white text-[#1a1a2e] shadow-sm' : 'text-gray-400 hover:text-[#1a1a2e]' }}"
                        >
                            @svg('heroicon-o-square-3-stack-3d', 'w-3.5 h-3.5')
                            <span>Workshop</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Meta strip --}}
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400">
                @if(($analysisData['strategy'] ?? null) === 'traffic_light')
                    <div class="inline-flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-full {{ match($analysisData['color'] ?? 'red') { 'green' => 'bg-green-500', 'yellow' => 'bg-yellow-500', default => 'bg-red-500' } }}"></span>
                        <span class="font-semibold text-[#1a1a2e]">{{ $analysisData['score'] ?? 0 }}%</span>
                        <span>{{ match($analysisData['color'] ?? 'red') { 'green' => 'Auf Kurs', 'yellow' => 'Aufmerksamkeit noetig', default => 'Kritisch' } }}</span>
                    </div>
                @endif
                <div class="inline-flex items-center gap-1">
                    @svg('heroicon-o-user', 'w-3.5 h-3.5')
                    {{ $canvas->createdByUser?->name ?? 'Unbekannt' }}
                </div>
                <div class="inline-flex items-center gap-1">
                    @svg('heroicon-o-calendar', 'w-3.5 h-3.5')
                    {{ $canvas->created_at?->format('d.m.Y') }}
                </div>
                <div class="inline-flex items-center gap-2">
                    <span>Fortschritt</span>
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
                    <div class="w-20 h-1.5 rounded-full bg-gray-100">
                        <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $analysisData['completeness_percent'] ?? 0 }}%"></div>
                    </div>
                    <span class="font-semibold text-[#1a1a2e]">{{ $analysisData['completeness_percent'] ?? 0 }}%</span>
                </div>
                <div>{{ $analysisData['filled_blocks'] ?? 0 }}/{{ $analysisData['total_blocks'] ?? 0 }} Bloecke &middot; {{ $analysisData['total_entries'] ?? 0 }} Eintraege</div>
            </div>

            @if($canvas->description)
                <p class="mt-2 text-xs text-gray-500 leading-relaxed">{{ $canvas->description }}</p>
            @endif
        </div>
    </header>

    {{-- Main --}}
    <main class="flex-1 min-h-0">
        @if($viewMode === 'list')
            {{-- ═══ LIST VIEW ═══ --}}
            <div class="p-4 sm:p-6 space-y-6 max-w-6xl mx-auto">
                @foreach($blockDefs as $i => $def)
                    <div id="block-{{ $def['key'] }}">
                        @include('planner::livewire.project-canvas._block', [
                            'blockKey' => $def['key'],
                            'blocks' => $canvasData['blocks'],
                            'blockDefs' => $blockDefs,
                            'blockIndex' => $i,
                        ])
                    </div>
                @endforeach
            </div>
        @else
            {{-- ═══ WORKSHOP VIEW (read-only) ═══ --}}
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
                        foreach ($tokens as $ci => $tok) {
                            $blockKey = $reverseMap[$tok] ?? $tok;
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

            <div
                x-data="publicCanvasViewer({ notes: {{ Js::from($workshopNotes) }} })"
                x-init="init()"
                class="relative overflow-hidden h-[calc(100vh-160px)]"
                style="background:#eef0f4;"
            >
                {{-- Zoom Controls --}}
                <div class="absolute top-3 right-3 z-20 flex items-center gap-1 bg-white rounded-full shadow-sm border border-gray-200 p-1">
                    <button x-on:click="zoomOut()" class="p-1.5 rounded-full hover:bg-gray-100" title="Zoom Out">
                        @svg('heroicon-o-minus', 'w-4 h-4 text-gray-600')
                    </button>
                    <span class="px-2 text-[11px] font-medium text-gray-600 tabular-nums" x-text="Math.round(scale * 100) + '%'"></span>
                    <button x-on:click="zoomIn()" class="p-1.5 rounded-full hover:bg-gray-100" title="Zoom In">
                        @svg('heroicon-o-plus', 'w-4 h-4 text-gray-600')
                    </button>
                    <button x-on:click="fitToScreen()" class="p-1.5 rounded-full hover:bg-gray-100 text-[10px] font-bold text-gray-600" title="An Bildschirm anpassen">Fit</button>
                </div>

                <div
                    x-ref="viewport"
                    class="relative w-full h-full overflow-hidden cursor-grab"
                    x-on:mousedown="startPan($event)"
                    x-on:wheel.prevent="onWheel($event)"
                >
                    <div
                        x-ref="board"
                        class="workshop-board"
                        :style="`width: {{ $boardW }}px; height: {{ $boardH }}px; transform: translate(${panX}px, ${panY}px) scale(${scale});`"
                    >
                        {{-- Canvas Grid (static) --}}
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

                        {{-- Notes (static, read-only) --}}
                        @foreach($workshopNotes as $n)
                            @php
                                $type = $n['type'] ?? 'note';
                                $color = $n['color'] ?? 'yellow';
                                $x = (int) ($n['x'] ?? 0);
                                $y = (int) ($n['y'] ?? 0);
                                $w = (int) ($n['width'] ?? 200);
                                $h = (int) ($n['height'] ?? 150);
                                $meta = $n['metadata'] ?? [];
                            @endphp

                            @if($type === 'connector')
                                @continue
                            @elseif($type === 'text')
                                <div class="workshop-text"
                                     style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; min-height:{{ $h }}px; font-weight:600; color:#1a1a2e; padding:4px 6px;">
                                    <div class="text-body">{{ $n['title'] ?: $n['content'] }}</div>
                                </div>
                            @elseif($type === 'section')
                                <div class="workshop-section workshop-section-{{ $color }}"
                                     style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; height:{{ $h }}px; border:2px dashed #c9c9d3; border-radius:8px; padding:8px; background:rgba(255,255,255,0.3);">
                                    @if($n['title'])
                                        <div class="text-xs font-bold text-[#1a1a2e]">{{ $n['title'] }}</div>
                                    @endif
                                </div>
                            @elseif($type === 'shape')
                                @php $shape = $meta['shape'] ?? 'rect'; @endphp
                                <div class="workshop-shape workshop-shape-{{ $shape }} workshop-shape-color-{{ $color }}"
                                     style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; height:{{ $h }}px; background:rgba(59,130,246,0.15); border:2px solid rgba(59,130,246,0.4); border-radius:{{ $shape === 'circle' ? '50%' : '4px' }}; display:flex; align-items:center; justify-content:center;">
                                    <span class="text-xs font-semibold text-[#1a1a2e]">{{ $n['title'] }}</span>
                                </div>
                            @elseif($type === 'image' && !empty($meta['url']))
                                <div style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; height:{{ $h }}px; border-radius:6px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                                    <img src="{{ $meta['url'] }}" alt="{{ $n['title'] }}" style="width:100%; height:100%; object-fit:cover;" />
                                </div>
                            @elseif($type === 'video' && !empty($meta['url']))
                                <div style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; height:{{ $h }}px; border-radius:6px; overflow:hidden; background:#000;">
                                    <video src="{{ $meta['url'] }}" controls style="width:100%; height:100%;"></video>
                                </div>
                            @elseif($type === 'kanban')
                                @php $cols = $meta['columns'] ?? []; @endphp
                                <div style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; height:{{ $h }}px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; padding:8px; display:flex; gap:6px; overflow:hidden;">
                                    @foreach($cols as $col)
                                        <div style="flex:1; background:#f3f4f6; border-radius:4px; padding:6px; min-width:0;">
                                            <div class="text-[11px] font-bold text-[#1a1a2e] mb-1.5 truncate">{{ $col['title'] ?? '' }}</div>
                                            <div class="space-y-1">
                                                @foreach(($col['cards'] ?? []) as $card)
                                                    <div class="text-[10px] bg-white border border-gray-200 rounded px-1.5 py-1 truncate">{{ $card['title'] ?? $card['content'] ?? '' }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- Default: sticky note --}}
                                <div class="workshop-note workshop-note-{{ $color }}"
                                     style="position:absolute; left:{{ $x }}px; top:{{ $y }}px; width:{{ $w }}px; min-height:{{ $h }}px; padding:8px 10px; border-radius:4px; box-shadow:0 2px 4px rgba(0,0,0,0.08);">
                                    @if(!empty($n['title']))
                                        <div class="text-[12px] font-bold text-[#1a1a2e] leading-tight">{{ $n['title'] }}</div>
                                    @endif
                                    @if(!empty($n['content']))
                                        <div class="text-[11px] text-[#1a1a2e]/80 mt-1 whitespace-pre-line leading-snug">{{ $n['content'] }}</div>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        {{-- Connectors as SVG overlay --}}
                        @php
                            $notesById = collect($workshopNotes)->keyBy('id');
                            $connectors = collect($workshopNotes)->filter(fn ($n) => ($n['type'] ?? '') === 'connector');
                        @endphp
                        @if($connectors->isNotEmpty())
                            <svg style="position:absolute; left:0; top:0; width:{{ $boardW }}px; height:{{ $boardH }}px; pointer-events:none;" xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <marker id="pub-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                        <path d="M0,0 L10,5 L0,10 z" fill="#6b7280"/>
                                    </marker>
                                </defs>
                                @foreach($connectors as $c)
                                    @php
                                        $cm = $c['metadata'] ?? [];
                                        $from = $notesById[$cm['fromNoteId'] ?? null] ?? null;
                                        $to = $notesById[$cm['toNoteId'] ?? null] ?? null;
                                    @endphp
                                    @if($from && $to)
                                        @php
                                            $x1 = ($from['x'] ?? 0) + (($from['width'] ?? 0) / 2);
                                            $y1 = ($from['y'] ?? 0) + (($from['height'] ?? 0) / 2);
                                            $x2 = ($to['x'] ?? 0) + (($to['width'] ?? 0) / 2);
                                            $y2 = ($to['y'] ?? 0) + (($to['height'] ?? 0) / 2);
                                        @endphp
                                        <line x1="{{ $x1 }}" y1="{{ $y1 }}" x2="{{ $x2 }}" y2="{{ $y2 }}" stroke="#6b7280" stroke-width="2" marker-end="url(#pub-arrow)" />
                                    @endif
                                @endforeach
                            </svg>
                        @endif
                    </div>
                </div>
            </div>

            <script>
            function publicCanvasViewer({ notes }) {
                return {
                    scale: 0.5,
                    panX: 0,
                    panY: 0,
                    minScale: 0.1,
                    maxScale: 2,
                    _dragging: false,
                    _startX: 0,
                    _startY: 0,
                    init() {
                        this.$nextTick(() => this.fitToScreen());
                    },
                    zoomIn() { this.scale = Math.min(this.maxScale, this.scale * 1.2); },
                    zoomOut() { this.scale = Math.max(this.minScale, this.scale / 1.2); },
                    fitToScreen() {
                        const vp = this.$refs.viewport;
                        if (!vp) return;
                        const sx = vp.clientWidth / {{ $boardW }};
                        const sy = vp.clientHeight / {{ $boardH }};
                        this.scale = Math.max(this.minScale, Math.min(sx, sy) * 0.95);
                        this.panX = (vp.clientWidth - {{ $boardW }} * this.scale) / 2;
                        this.panY = (vp.clientHeight - {{ $boardH }} * this.scale) / 2;
                    },
                    startPan(e) {
                        if (e.button !== 0) return;
                        this._dragging = true;
                        this._startX = e.clientX - this.panX;
                        this._startY = e.clientY - this.panY;
                        const move = (ev) => {
                            if (!this._dragging) return;
                            this.panX = ev.clientX - this._startX;
                            this.panY = ev.clientY - this._startY;
                        };
                        const up = () => {
                            this._dragging = false;
                            window.removeEventListener('mousemove', move);
                            window.removeEventListener('mouseup', up);
                        };
                        window.addEventListener('mousemove', move);
                        window.addEventListener('mouseup', up);
                    },
                    onWheel(e) {
                        const delta = -e.deltaY * 0.001;
                        const newScale = Math.max(this.minScale, Math.min(this.maxScale, this.scale * (1 + delta)));
                        const vp = this.$refs.viewport.getBoundingClientRect();
                        const mouseX = e.clientX - vp.left;
                        const mouseY = e.clientY - vp.top;
                        this.panX = mouseX - (mouseX - this.panX) * (newScale / this.scale);
                        this.panY = mouseY - (mouseY - this.panY) * (newScale / this.scale);
                        this.scale = newScale;
                    },
                };
            }
            </script>
        @endif
    </main>
</div>
