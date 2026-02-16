<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projekt: {{ $project['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 10pt;
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
            font-size: 20pt;
            font-weight: 700;
            color: #111827;
            margin: 8px 0 4px 0;
        }
        .project-meta {
            font-size: 10pt;
            color: #6b7280;
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
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-done { background: #e5e7eb; color: #374151; }
        .badge-type { background: #dbeafe; color: #1e40af; }

        h2 {
            font-size: 14pt;
            font-weight: 700;
            color: #111827;
            margin: 28px 0 12px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }
        h3 {
            font-size: 12pt;
            font-weight: 600;
            color: #374151;
            margin: 20px 0 10px 0;
        }

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
            padding: 3px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .field-label {
            width: 180px;
            font-weight: 600;
            color: #6b7280;
            font-size: 9pt;
            flex-shrink: 0;
        }
        .field-value {
            flex: 1;
            color: #1f2937;
            font-size: 9pt;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .stat-value {
            font-size: 20pt;
            font-weight: 700;
            color: #111827;
        }
        .stat-label {
            font-size: 8pt;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .members-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .member-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: #f3f4f6;
            border-radius: 16px;
            font-size: 9pt;
        }
        .member-role {
            font-size: 8pt;
            color: #6b7280;
        }

        .slot-section {
            margin: 16px 0;
            page-break-inside: avoid;
        }
        .slot-header {
            background: #f0f4ff;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 8px;
        }
        .slot-name {
            font-size: 12pt;
            font-weight: 600;
            color: #1e40af;
        }
        .slot-count {
            font-size: 9pt;
            color: #6b7280;
        }

        .task-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 12px;
        }
        .task-table th {
            text-align: left;
            padding: 6px 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .task-table td {
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .task-table tr:nth-child(even) {
            background: #fafafa;
        }
        .task-done {
            color: #9ca3af;
        }
        .task-done .task-title {
            text-decoration: line-through;
        }
        .task-status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .dot-open { background: #fbbf24; }
        .dot-done { background: #34d399; }

        .description-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            font-size: 9pt;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-bottom: 12px;
        }

        .extra-fields-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        .extra-fields-table th {
            text-align: left;
            padding: 5px 8px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }
        .extra-fields-table td {
            padding: 5px 8px;
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
            body { padding: 15px; }
            .no-print { display: none; }
            .slot-section { page-break-inside: avoid; }
            h2 { page-break-after: avoid; }
        }

        @page {
            size: A4;
            margin: 15mm;
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

    {{-- Header --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="brand">Planner – Projektexport</div>
                @if(!empty($project['team']))
                    <div style="font-size: 9pt; color: #9ca3af;">Team: {{ $project['team']['name'] }}</div>
                @endif
            </div>
            <div class="export-date">Exportiert: {{ $exportedAt }}</div>
        </div>
        <h1>{{ $project['name'] }}</h1>
        <div class="meta-badges">
            <span class="badge {{ $project['done'] ? 'badge-done' : 'badge-active' }}">
                {{ $project['done'] ? 'Abgeschlossen' : 'Aktiv' }}
            </span>
            @if(!empty($project['project_type_label']))
                <span class="badge badge-type">{{ $project['project_type_label'] }}</span>
            @endif
        </div>
    </div>

    {{-- Statistiken --}}
    @if(!empty($project['statistics']))
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ $project['statistics']['total_tasks'] }}</div>
                <div class="stat-label">Aufgaben gesamt</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $project['statistics']['open_tasks'] }}</div>
                <div class="stat-label">Offen</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $project['statistics']['completed_tasks'] }}</div>
                <div class="stat-label">Erledigt</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $project['statistics']['total_story_points'] }}</div>
                <div class="stat-label">Story Points</div>
            </div>
        </div>
    @endif

    {{-- Projektdetails --}}
    <div class="section">
        <div class="section-title">Projektdetails</div>
        <div class="field-row">
            <div class="field-label">ID / UUID</div>
            <div class="field-value">#{{ $project['id'] }} &middot; {{ $project['uuid'] }}</div>
        </div>
        @if(!empty($project['creator']))
            <div class="field-row">
                <div class="field-label">Erstellt von</div>
                <div class="field-value">{{ $project['creator']['name'] }} ({{ $project['creator']['email'] }})</div>
            </div>
        @endif
        @if(!empty($project['planned_minutes']))
            <div class="field-row">
                <div class="field-label">Geplante Zeit</div>
                <div class="field-value">{{ floor($project['planned_minutes'] / 60) }}h {{ $project['planned_minutes'] % 60 }}min</div>
            </div>
        @endif
        @if(!empty($project['description']))
            <div class="field-row">
                <div class="field-label">Beschreibung</div>
                <div class="field-value">{{ $project['description'] }}</div>
            </div>
        @endif
        <div class="field-row">
            <div class="field-label">Erstellt am</div>
            <div class="field-value">{{ $project['created_at'] ? \Carbon\Carbon::parse($project['created_at'])->format('d.m.Y H:i') : '–' }}</div>
        </div>
        @if(!empty($project['done_at']))
            <div class="field-row">
                <div class="field-label">Abgeschlossen am</div>
                <div class="field-value">{{ \Carbon\Carbon::parse($project['done_at'])->format('d.m.Y H:i') }}</div>
            </div>
        @endif
    </div>

    {{-- Team-Mitglieder --}}
    @if(!empty($project['members']))
        <div class="section">
            <div class="section-title">Team-Mitglieder ({{ count($project['members']) }})</div>
            <div class="members-list">
                @foreach($project['members'] as $member)
                    <div class="member-badge">
                        {{ $member['name'] ?? $member['email'] ?? 'Unbekannt' }}
                        <span class="member-role">({{ ucfirst($member['role'] ?? 'member') }})</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Extrafelder --}}
    @if(!empty($project['extra_fields']))
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
                    @foreach($project['extra_fields'] as $field)
                        <tr>
                            <td>{{ $field['label'] ?? $field['key'] ?? '–' }}</td>
                            <td>{{ $field['value'] ?? '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Slots mit Aufgaben --}}
    @if(!empty($project['slots']))
        <h2>Aufgaben nach Slots</h2>
        @foreach($project['slots'] as $slot)
            <div class="slot-section">
                <div class="slot-header">
                    <span class="slot-name">{{ $slot['name'] }}</span>
                    <span class="slot-count">&middot; {{ count($slot['tasks']) }} Aufgaben</span>
                </div>

                @if(!empty($slot['tasks']))
                    <table class="task-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Aufgabe</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 70px;">Priorität</th>
                                <th style="width: 40px;">SP</th>
                                <th style="width: 110px;">Verantwortlich</th>
                                <th style="width: 80px;">Fällig</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($slot['tasks'] as $task)
                                <tr class="{{ $task['is_done'] ? 'task-done' : '' }}">
                                    <td>{{ $task['id'] }}</td>
                                    <td>
                                        <span class="task-title">{{ $task['title'] }}</span>
                                        @if(!empty($task['dod']['progress']['total']))
                                            <span style="font-size: 8pt; color: #6b7280;">
                                                (DoD: {{ $task['dod']['progress']['checked'] }}/{{ $task['dod']['progress']['total'] }})
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="task-status-dot {{ $task['is_done'] ? 'dot-done' : 'dot-open' }}"></span>
                                        {{ $task['is_done'] ? 'Erledigt' : 'Offen' }}
                                    </td>
                                    <td>{{ $task['priority_label'] ?? '–' }}</td>
                                    <td>{{ $task['story_points_label'] ?? '–' }}</td>
                                    <td>{{ $task['assignee']['name'] ?? '–' }}</td>
                                    <td>{{ $task['due_date'] ? \Carbon\Carbon::parse($task['due_date'])->format('d.m.Y') : '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p style="color: #9ca3af; font-size: 9pt; padding: 8px;">Keine Aufgaben in diesem Slot.</p>
                @endif
            </div>
        @endforeach
    @endif

    {{-- Backlog-Aufgaben --}}
    @if(!empty($project['backlog_tasks']))
        <div class="slot-section">
            <div class="slot-header" style="background: #fef3c7; border-color: #fde68a;">
                <span class="slot-name" style="color: #92400e;">Backlog</span>
                <span class="slot-count">&middot; {{ count($project['backlog_tasks']) }} Aufgaben</span>
            </div>

            <table class="task-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>Aufgabe</th>
                        <th style="width: 80px;">Status</th>
                        <th style="width: 70px;">Priorität</th>
                        <th style="width: 40px;">SP</th>
                        <th style="width: 110px;">Verantwortlich</th>
                        <th style="width: 80px;">Fällig</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project['backlog_tasks'] as $task)
                        <tr class="{{ $task['is_done'] ? 'task-done' : '' }}">
                            <td>{{ $task['id'] }}</td>
                            <td>
                                <span class="task-title">{{ $task['title'] }}</span>
                                @if(!empty($task['dod']['progress']['total']))
                                    <span style="font-size: 8pt; color: #6b7280;">
                                        (DoD: {{ $task['dod']['progress']['checked'] }}/{{ $task['dod']['progress']['total'] }})
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="task-status-dot {{ $task['is_done'] ? 'dot-done' : 'dot-open' }}"></span>
                                {{ $task['is_done'] ? 'Erledigt' : 'Offen' }}
                            </td>
                            <td>{{ $task['priority_label'] ?? '–' }}</td>
                            <td>{{ $task['story_points_label'] ?? '–' }}</td>
                            <td>{{ $task['assignee']['name'] ?? '–' }}</td>
                            <td>{{ $task['due_date'] ? \Carbon\Carbon::parse($task['due_date'])->format('d.m.Y') : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="footer">
        Exportiert am {{ $exportedAt }} &middot; Planner Projektexport &middot; {{ $project['name'] }} (#{{ $project['id'] }})
    </div>
</body>
</html>
