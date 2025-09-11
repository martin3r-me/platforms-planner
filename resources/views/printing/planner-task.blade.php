@php
    /** @var \Platform\Planner\Models\PlannerTask $printable */
    /** @var \Platform\Printing\Models\PrintJob $job */
    
    // Bon-Drucker optimierte Formatierung
    $width = 48; // 80mm = ~48 Zeichen
    $separator = str_repeat('=', $width);
    $line = str_repeat('-', $width);
@endphp

{{ $separator }}
{{ str_pad('TASK #' . $printable->id, $width, ' ', STR_PAD_BOTH) }}
{{ $separator }}

{{ str_pad('TITEL:', 15, ' ') }}{{ Str::limit($printable->title, $width - 15) }}
{{ str_pad('PRIORITAT:', 15, ' ') }}{{ $printable->priority?->label() ?? 'Normal' }}
{{ str_pad('STATUS:', 15, ' ') }}{{ $printable->is_done ? 'Erledigt' : 'Offen' }}

@if($printable->is_frog)
{{ str_pad('FROSCH:', 15, ' ') }}JA - Wichtige Aufgabe!
@endif

@if($printable->description)
{{ $line }}
{{ str_pad('BESCHREIBUNG:', 15, ' ') }}
{{ wordwrap($printable->description, $width, "\n", true) }}
@endif

{{ $line }}
{{ str_pad('DETAILS:', 15, ' ') }}
{{ str_pad('Erstellt:', 15, ' ') }}{{ $printable->created_at->format('d.m.Y H:i') }}
@if($printable->due_date)
{{ str_pad('Fällig:', 15, ' ') }}{{ $printable->due_date->format('d.m.Y') }}
@endif
@if($printable->story_points)
{{ str_pad('Story Points:', 15, ' ') }}{{ $printable->story_points?->label() ?? '–' }}
@endif

@if($printable->project)
{{ $line }}
{{ str_pad('PROJEKT INFO:', 15, ' ') }}
{{ str_pad('Projekt:', 15, ' ') }}{{ Str::limit($printable->project->name, $width - 15) }}
@if($printable->user_in_charge_id)
@php
    $assignedUser = collect($printable->project->projectUsers ?? [])
        ->firstWhere('user.id', $printable->user_in_charge_id);
@endphp
@if($assignedUser)
{{ str_pad('Verantwortlich:', 15, ' ') }}{{ Str::limit($assignedUser['user']['fullname'] ?? 'Unbekannt', $width - 15) }}
@endif
@endif
@endif

@if(isset($data['requested_by']))
{{ $line }}
{{ str_pad('Gedruckt von:', 15, ' ') }}{{ Str::limit($data['requested_by'], $width - 15) }}
@endif

{{ $separator }}
{{ str_pad(now()->format('d.m.Y H:i:s'), $width, ' ', STR_PAD_BOTH) }}
{{ $separator }}

{{ "\n\n\n" }}
