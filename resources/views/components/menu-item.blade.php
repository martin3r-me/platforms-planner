{{-- Eintrag in den Menüs der Planner-Actionbar ("+ Neu", "⋯"). Mit href → Link, sonst Button.
     Weitere Attribute (wire:click, @click, title …) werden durchgereicht. --}}
@props([
    'icon' => null,
    'href' => null,
    'navigate' => true,
    'danger' => false,
    'hint' => null,
])

@php
    $classes = 'w-full flex items-center gap-2.5 px-3 py-2 sm:py-1.5 text-[13px] sm:text-xs text-left rounded-md transition-colors '
        . ($danger ? 'text-[color:var(--nx-danger)] hover:bg-[color:var(--nx-danger)]/10' : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]');
@endphp

@if($href)
    <a href="{{ $href }}" @if($navigate) wire:navigate @endif data-menu-item {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon) @svg($icon, 'w-4 h-4 shrink-0 ' . ($danger ? '' : 'text-[color:var(--nx-muted)]')) @endif
        <span class="flex-1 min-w-0 truncate">{{ $slot }}</span>
        @if($hint)<span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">{{ $hint }}</span>@endif
    </a>
@else
    <button type="button" data-menu-item {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon) @svg($icon, 'w-4 h-4 shrink-0 ' . ($danger ? '' : 'text-[color:var(--nx-muted)]')) @endif
        <span class="flex-1 min-w-0 truncate">{{ $slot }}</span>
        @if($hint)<span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">{{ $hint }}</span>@endif
    </button>
@endif
