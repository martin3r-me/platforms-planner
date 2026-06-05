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

        /* -- Font scale tiers -- */
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

        /* -- Canvas table (3x3 grid) -- */
        .canvas-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .canvas-table td {
            width: 33.33%;
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

        .entry-type {
            font-size: 0.8em;
            color: #9ca3af;
            font-style: italic;
        }

        .entry-content {
            font-size: 0.9em;
            color: #6b7280;
            margin-top: 0.2mm;
            word-wrap: break-word;
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

        $getBlock = function($type) use ($blocks, $blockTypes) {
            $block = $blocks[$type] ?? null;
            $config = $blockTypes[$type] ?? [];
            $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $type));
            $entries = $block['entries'] ?? [];
            return ['label' => $label, 'entries' => $entries];
        };

        $grid = [
            [$getBlock('project_goal'), $getBlock('scope'), $getBlock('stakeholders')],
            [$getBlock('risks'), $getBlock('milestones'), $getBlock('resources')],
            [$getBlock('budget'), $getBlock('communication'), $getBlock('governance')],
        ];
    @endphp

    <table class="canvas-table">
        @foreach($grid as $row)
        <tr>
            @foreach($row as $block)
            <td>
                <div class="block-header"><h3>{{ $block['label'] }}</h3></div>
                <div class="block-body">
                    @forelse($block['entries'] as $entry)
                        <div class="entry">
                            @if(!empty($entry['title']))
                                <span class="entry-title">{{ $entry['title'] }}</span>
                                @if(!empty($entry['entry_type']) && $entry['entry_type'] !== 'text')
                                    <span class="entry-type">({{ ucfirst($entry['entry_type']) }})</span>
                                @endif
                            @endif
                            @if(!empty($entry['content']))<div class="entry-content">{{ $entry['content'] }}</div>@endif
                        </div>
                    @empty
                        <div class="empty-hint">&ndash;</div>
                    @endforelse
                </div>
            </td>
            @endforeach
        </tr>
        @endforeach
    </table>

    {{-- Footer --}}
    <div class="footer">
        {{ $canvas->name }} &middot; Erstellt am {{ $canvas->created_at?->format('d.m.Y H:i') }}
        @if($canvas->createdByUser) von {{ $canvas->createdByUser->name }} @endif
        &middot; Project Canvas
    </div>
</body>
</html>
