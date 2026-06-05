<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $canvas->name }} - Project Canvas</title>
    <style>
        @page {
            margin: 8mm 10mm;
            size: A4 landscape;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            line-height: 1.3;
            color: #1f2937;
        }

        /* Font scale tiers */
        body.scale-lg { font-size: 8pt; }
        body.scale-md { font-size: 7pt; }
        body.scale-sm { font-size: 6pt; }
        body.scale-xs { font-size: 5pt; }

        .header {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 0.5pt solid #d1d5db;
        }

        .scale-lg .header h1 { font-size: 14pt; }
        .scale-md .header h1 { font-size: 13pt; }
        .scale-sm .header h1 { font-size: 11pt; }
        .scale-xs .header h1 { font-size: 10pt; }

        .header h1 {
            font-weight: bold;
            color: #111827;
            margin-bottom: 1mm;
        }

        .header .meta {
            font-size: 0.85em;
            color: #6b7280;
        }

        .canvas-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .canvas-table td {
            border: 0.5pt solid #d1d5db;
            vertical-align: top;
            padding: 0;
        }

        .block-header {
            background: #f3f4f6;
            padding: 1.5mm 2mm;
            border-bottom: 0.5pt solid #d1d5db;
        }

        .block-header h3 {
            font-size: 1em;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            color: #374151;
        }

        .block-body {
            padding: 1.5mm 2mm;
        }

        .entry {
            margin-bottom: 1mm;
            padding: 0.8mm 1.2mm;
            background: #f9fafb;
            border: 0.3pt solid #e5e7eb;
            border-radius: 0.5mm;
        }

        .entry:last-child {
            margin-bottom: 0;
        }

        .entry-title {
            font-weight: bold;
            color: #1f2937;
        }

        .entry-content {
            font-size: 0.9em;
            color: #6b7280;
            margin-top: 0.2mm;
            word-wrap: break-word;
        }

        .entry-type-badge {
            font-size: 0.75em;
            color: #9ca3af;
            font-style: italic;
        }

        .empty-hint {
            color: #9ca3af;
            font-style: italic;
            text-align: center;
            padding: 2mm 0;
        }

        .footer {
            margin-top: 2mm;
            font-size: 0.8em;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body class="scale-{{ $fontScale }}">
    {{-- Header --}}
    <div class="header">
        <h1>{{ $canvas->name }}</h1>
        <div class="meta">
            Project Canvas
            @if($canvas->createdByUser) &middot; {{ $canvas->createdByUser->name }} @endif
            &middot; {{ $canvas->created_at?->format('d.m.Y') }}
            @if($canvas->description) &middot; {{ $canvas->description }} @endif
        </div>
    </div>

    @php
        $blocks = $canvasData['blocks'] ?? [];
        $layout = config('planner.canvas_layout', []);
        $hasAreas = !empty($layout['areas'] ?? null) && !empty($layout['area_map'] ?? null);
        $columns = $layout['columns'] ?? 3;
        $rows = $layout['rows'] ?? 3;

        $getBlock = function($key) use ($blocks, $blockDefs) {
            $block = $blocks[$key] ?? null;
            $config = collect($blockDefs)->firstWhere('key', $key) ?? [];
            $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));
            $entries = $block['entries'] ?? [];
            return ['label' => $label, 'entries' => $entries];
        };
    @endphp

    @if($hasAreas)
        {{-- Complex layout: parse areas string into 2D grid for HTML table --}}
        @php
            $areasRows = array_map('trim', explode('/', $layout['areas']));
            $areaMap = $layout['area_map'];
            $reverseMap = array_flip($areaMap);

            $grid = [];
            foreach ($areasRows as $rowIdx => $rowStr) {
                $grid[$rowIdx] = preg_split('/\s+/', $rowStr);
            }

            $numRows = count($grid);
            $numCols = count($grid[0] ?? []);

            $rendered = [];
            $colWidth = round(100 / $numCols, 4);
        @endphp

        <table class="canvas-table">
            @for($r = 0; $r < $numRows; $r++)
            <tr>
                @for($c = 0; $c < $numCols; $c++)
                    @php
                        $areaCode = $grid[$r][$c] ?? '';

                        if (isset($rendered[$areaCode])) {
                            continue;
                        }

                        $colspan = 1;
                        while ($c + $colspan < $numCols && ($grid[$r][$c + $colspan] ?? '') === $areaCode) {
                            $colspan++;
                        }

                        $rowspan = 1;
                        while ($r + $rowspan < $numRows) {
                            $match = true;
                            for ($cc = $c; $cc < $c + $colspan; $cc++) {
                                if (($grid[$r + $rowspan][$cc] ?? '') !== $areaCode) {
                                    $match = false;
                                    break;
                                }
                            }
                            if (!$match) break;
                            $rowspan++;
                        }

                        $rendered[$areaCode] = true;
                        $blockKey = $reverseMap[$areaCode] ?? null;
                        $blockData = $blockKey ? $getBlock($blockKey) : null;
                    @endphp

                    @if($blockData)
                    <td @if($colspan > 1) colspan="{{ $colspan }}" @endif
                        @if($rowspan > 1) rowspan="{{ $rowspan }}" @endif
                        style="width: {{ $colWidth * $colspan }}%;">
                        <div class="block-header"><h3>{{ $blockData['label'] }}</h3></div>
                        <div class="block-body">
                            @forelse($blockData['entries'] as $entry)
                                <div class="entry">
                                    @if(!empty($entry['title']))<div class="entry-title">{{ $entry['title'] }}</div>@endif
                                    @if(($entry['entry_type'] ?? 'text') !== 'text')<span class="entry-type-badge">[{{ $entry['entry_type'] }}]</span>@endif
                                    @if(!empty($entry['content']))<div class="entry-content">{{ $entry['content'] }}</div>@endif
                                </div>
                            @empty
                                <div class="empty-hint">&ndash;</div>
                            @endforelse
                        </div>
                    </td>
                    @endif
                @endfor
            </tr>
            @endfor
        </table>
    @else
        {{-- Simple grid: NxM table --}}
        @php
            $orderedDefs = collect($blockDefs)->sortBy('position')->values();
            $chunks = $orderedDefs->chunk($columns);
        @endphp

        <table class="canvas-table">
            @foreach($chunks as $row)
            <tr>
                @foreach($row as $def)
                    @php $blockData = $getBlock($def['key']); @endphp
                    <td style="width: {{ round(100 / $columns, 4) }}%;">
                        <div class="block-header"><h3>{{ $blockData['label'] }}</h3></div>
                        <div class="block-body">
                            @forelse($blockData['entries'] as $entry)
                                <div class="entry">
                                    @if(!empty($entry['title']))<div class="entry-title">{{ $entry['title'] }}</div>@endif
                                    @if(($entry['entry_type'] ?? 'text') !== 'text')<span class="entry-type-badge">[{{ $entry['entry_type'] }}]</span>@endif
                                    @if(!empty($entry['content']))<div class="entry-content">{{ $entry['content'] }}</div>@endif
                                </div>
                            @empty
                                <div class="empty-hint">&ndash;</div>
                            @endforelse
                        </div>
                    </td>
                @endforeach
                {{-- Fill remaining cells if last row is incomplete --}}
                @for($i = $row->count(); $i < $columns; $i++)
                    <td style="width: {{ round(100 / $columns, 4) }}%;"></td>
                @endfor
            </tr>
            @endforeach
        </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        {{ $canvas->name }} &middot; Erstellt am {{ $canvas->created_at?->format('d.m.Y H:i') }}
        @if($canvas->createdByUser) von {{ $canvas->createdByUser->name }} @endif
        &middot; Project Canvas
    </div>
</body>
</html>
