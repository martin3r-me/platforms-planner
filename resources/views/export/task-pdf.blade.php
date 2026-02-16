<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aufgabe: {{ $task['title'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1a1a1a;
            padding: 40px;
            background: #fff;
        }
        .header {
            border-bottom: 3px solid #2563eb;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .brand {
            font-size: 10pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .export-date {
            font-size: 9pt;
            color: #9ca3af;
        }
        h1 {
            font-size: 18pt;
            font-weight: 700;
            color: #111827;
            margin: 8px 0;
        }
        .meta-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: 600;
        }
        .badge-status-open { background: #fef3c7; color: #92400e; }
        .badge-status-done { background: #d1fae5; color: #065f46; }
        .badge-priority-high { background: #fee2e2; color: #991b1b; }
        .badge-priority-normal { background: #e5e7eb; color: #374151; }
        .badge-priority-low { background: #dbeafe; color: #1e40af; }
        .badge-frog { background: #d1fae5; color: #065f46; }

        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 11pt;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }
        .field-row {
            display: flex;
            padding: 4px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .field-label {
            width: 180px;
            font-weight: 600;
            color: #6b7280;
            font-size: 10pt;
            flex-shrink: 0;
        }
        .field-value {
            flex: 1;
            color: #1f2937;
            font-size: 10pt;
        }
        .description-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            font-size: 10pt;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .dod-list {
            list-style: none;
            padding: 0;
        }
        .dod-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            font-size: 10pt;
        }
        .dod-checkbox {
            width: 14px;
            height: 14px;
            border: 2px solid #d1d5db;
            border-radius: 3px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dod-checkbox.checked {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .dod-checkbox.checked::after { content: '\2713'; font-size: 10px; }
        .dod-item.checked .dod-text {
            text-decoration: line-through;
            color: #9ca3af;
        }
        .dod-progress {
            margin-top: 8px;
            font-size: 9pt;
            color: #6b7280;
        }
        .extra-fields-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        .extra-fields-table th {
            text-align: left;
            padding: 6px 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        .extra-fields-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
        }
        .footer {
            margin-top: 40px;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            color: #9ca3af;
            text-align: center;
        }

        @media print {
            body { padding: 20px; }
            .no-print { display: none; }
        }

        @page {
            size: A4;
            margin: 20mm;
        }
    </style>
</head>
<body>
    {{-- Print-Button (nur im Browser sichtbar) --}}
    <div class="no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 10pt;">
            Als PDF drucken / speichern
        </button>
    </div>

    <div class="header">
        <div class="header-top">
            <div>
                <div class="brand">Planner Export</div>
                @if(!empty($task['team']))
                    <div style="font-size: 9pt; color: #9ca3af;">Team: {{ $task['team']['name'] }}</div>
                @endif
            </div>
            <div class="export-date">Exportiert: {{ $exportedAt }}</div>
        </div>
        <h1>{{ $task['title'] }}</h1>
        <div class="meta-badges">
            <span class="badge {{ $task['is_done'] ? 'badge-status-done' : 'badge-status-open' }}">
                {{ $task['status'] }}
            </span>
            @if(!empty($task['priority']))
                <span class="badge badge-priority-{{ $task['priority'] }}">
                    {{ $task['priority_label'] ?? ucfirst($task['priority']) }}
                </span>
            @endif
            @if(!empty($task['story_points_label']))
                <span class="badge" style="background: #ede9fe; color: #5b21b6;">
                    {{ $task['story_points_label'] }} ({{ $task['story_points_numeric'] }} Pt.)
                </span>
            @endif
            @if(!empty($task['is_frog']))
                <span class="badge badge-frog">Frosch</span>
            @endif
        </div>
    </div>

    {{-- Aufgabendetails --}}
    <div class="section">
        <div class="section-title">Details</div>
        <div class="field-row">
            <div class="field-label">ID / UUID</div>
            <div class="field-value">#{{ $task['id'] }} &middot; {{ $task['uuid'] }}</div>
        </div>
        @if(!empty($task['creator']))
            <div class="field-row">
                <div class="field-label">Erstellt von</div>
                <div class="field-value">{{ $task['creator']['name'] }} ({{ $task['creator']['email'] }})</div>
            </div>
        @endif
        @if(!empty($task['assignee']))
            <div class="field-row">
                <div class="field-label">Verantwortlich</div>
                <div class="field-value">{{ $task['assignee']['name'] }} ({{ $task['assignee']['email'] }})</div>
            </div>
        @endif
        @if(!empty($task['project']))
            <div class="field-row">
                <div class="field-label">Projekt</div>
                <div class="field-value">{{ $task['project']['name'] }}</div>
            </div>
        @endif
        @if(!empty($task['project_slot']))
            <div class="field-row">
                <div class="field-label">Slot / Phase</div>
                <div class="field-value">{{ $task['project_slot']['name'] }}</div>
            </div>
        @endif
        @if(!empty($task['task_group']))
            <div class="field-row">
                <div class="field-label">Aufgabengruppe</div>
                <div class="field-value">{{ $task['task_group']['label'] }}</div>
            </div>
        @endif
        @if(!empty($task['due_date']))
            <div class="field-row">
                <div class="field-label">Fällig am</div>
                <div class="field-value">
                    {{ \Carbon\Carbon::parse($task['due_date'])->format('d.m.Y') }}
                    @if(!empty($task['original_due_date']) && $task['original_due_date'] !== $task['due_date'])
                        <span style="color: #ef4444; font-size: 9pt;">(Ursprünglich: {{ \Carbon\Carbon::parse($task['original_due_date'])->format('d.m.Y') }}, {{ $task['postpone_count'] }}x verschoben)</span>
                    @endif
                </div>
            </div>
        @endif
        @if(!empty($task['planned_minutes']))
            <div class="field-row">
                <div class="field-label">Geplante Zeit</div>
                <div class="field-value">{{ floor($task['planned_minutes'] / 60) }}h {{ $task['planned_minutes'] % 60 }}min</div>
            </div>
        @endif
        @if(!empty($task['done_at']))
            <div class="field-row">
                <div class="field-label">Erledigt am</div>
                <div class="field-value">{{ \Carbon\Carbon::parse($task['done_at'])->format('d.m.Y H:i') }}</div>
            </div>
        @endif
        <div class="field-row">
            <div class="field-label">Erstellt am</div>
            <div class="field-value">{{ $task['created_at'] ? \Carbon\Carbon::parse($task['created_at'])->format('d.m.Y H:i') : '–' }}</div>
        </div>
    </div>

    {{-- Beschreibung --}}
    @if(!empty($task['description']))
        <div class="section">
            <div class="section-title">Beschreibung</div>
            <div class="description-box">{{ $task['description'] }}</div>
        </div>
    @endif

    {{-- Definition of Done --}}
    @if(!empty($task['dod']['items']))
        <div class="section">
            <div class="section-title">Definition of Done</div>
            <ul class="dod-list">
                @foreach($task['dod']['items'] as $item)
                    <li class="dod-item {{ $item['checked'] ? 'checked' : '' }}">
                        <span class="dod-checkbox {{ $item['checked'] ? 'checked' : '' }}"></span>
                        <span class="dod-text">{{ $item['text'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="dod-progress">
                Fortschritt: {{ $task['dod']['progress']['checked'] }}/{{ $task['dod']['progress']['total'] }}
                ({{ $task['dod']['progress']['percentage'] }}%)
            </div>
        </div>
    @endif

    {{-- Extrafelder --}}
    @if(!empty($task['extra_fields']))
        <div class="section">
            <div class="section-title">Extrafelder</div>
            <table class="extra-fields-table">
                <thead>
                    <tr>
                        <th>Feld</th>
                        <th>Wert</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($task['extra_fields'] as $field)
                        <tr>
                            <td>{{ $field['label'] ?? $field['key'] ?? '–' }}</td>
                            <td>{{ $field['value'] ?? '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Exportiert am {{ $exportedAt }} &middot; Planner Export &middot; Aufgabe #{{ $task['id'] }}
    </div>
</body>
</html>
