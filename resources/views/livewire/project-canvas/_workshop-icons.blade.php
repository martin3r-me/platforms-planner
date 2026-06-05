@php
    $types = [
        ['count' => $canvas->ws_notes_count ?? 0, 'icon' => 'heroicon-o-chat-bubble-bottom-center-text', 'label' => 'Notizen'],
        ['count' => $canvas->ws_text_count ?? 0, 'icon' => 'heroicon-o-bars-3-bottom-left', 'label' => 'Texte'],
        ['count' => $canvas->ws_image_count ?? 0, 'icon' => 'heroicon-o-photo', 'label' => 'Bilder'],
        ['count' => $canvas->ws_video_count ?? 0, 'icon' => 'heroicon-o-video-camera', 'label' => 'Videos'],
        ['count' => $canvas->ws_kanban_count ?? 0, 'icon' => 'heroicon-o-view-columns', 'label' => 'Kanban'],
        ['count' => $canvas->ws_section_count ?? 0, 'icon' => 'heroicon-o-rectangle-group', 'label' => 'Sektionen'],
        ['count' => $canvas->ws_shape_count ?? 0, 'icon' => 'heroicon-o-stop', 'label' => 'Formen'],
        ['count' => $canvas->ws_connector_count ?? 0, 'icon' => 'heroicon-o-arrows-right-left', 'label' => 'Verbindungen'],
    ];
    $hasAny = collect($types)->sum('count') > 0;
@endphp
@if($hasAny)
<div class="flex items-center gap-1.5">
    @foreach($types as $t)
        @if($t['count'] > 0)
        <span class="inline-flex items-center gap-0.5 text-gray-400" title="{{ $t['count'] }} {{ $t['label'] }}">
            @svg($t['icon'], 'w-3.5 h-3.5')
            <span class="text-[10px] font-medium">{{ $t['count'] }}</span>
        </span>
        @endif
    @endforeach
</div>
@endif
