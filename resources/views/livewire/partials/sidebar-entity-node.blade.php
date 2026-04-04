{{-- Rekursiver Entity-Knoten für Sidebar-Baum --}}
@props(['node', 'typeIcon' => null])

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('planner.entity.' + {{ $node['entity_id'] }}) === 'true' }"
     class="flex flex-col">
    <button type="button"
            @click="open = !open; localStorage.setItem('planner.entity.' + {{ $node['entity_id'] }}, open)"
            class="flex items-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition w-full text-left">
        <span class="w-4 h-4 flex-shrink-0 flex items-center justify-center transition-transform"
              :class="open ? 'rotate-90' : ''">
            @svg('heroicon-o-chevron-right', 'w-3 h-3')
        </span>
        @if($typeIcon && str_starts_with($typeIcon, 'heroicon-'))
            @svg($typeIcon, 'w-4 h-4 flex-shrink-0 ml-1 text-[var(--ui-muted)]')
        @else
            @svg('heroicon-o-rectangle-group', 'w-4 h-4 flex-shrink-0 ml-1 text-[var(--ui-muted)]')
        @endif
        <span class="ml-1.5 text-sm font-medium truncate">{{ $node['entity_name'] }}</span>
        <span class="ml-auto text-xs text-[var(--ui-muted)]">{{ $node['total_projects'] }}</span>
    </button>
    <div x-show="open" x-collapse class="flex flex-col gap-0.5 pl-4">
        {{-- 1. Eigene Projekte zuerst --}}
        @foreach($node['projects'] as $project)
            <a wire:key="entity-{{ $node['entity_id'] }}-project-{{ $project->id }}"
               href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
               wire:navigate
               title="{{ $project->name }}"
               class="flex items-center gap-1.5 py-1 px-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                @svg('heroicon-o-folder', 'w-3.5 h-3.5 flex-shrink-0 text-[var(--ui-muted)]')
                <span class="truncate text-xs">{{ $project->name }}</span>
                @if($project->color)
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ml-auto" style="background-color: {{ $project->color }}"></span>
                @endif
            </a>
        @endforeach
        {{-- 2. Kind-Entities nach Typ gruppiert, ein-/ausklappbar --}}
        @foreach($node['children_by_type'] as $typeGroup)
            <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}"
                 x-data="{ groupOpen: localStorage.getItem('planner.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}) !== 'false' }"
                 class="flex flex-col">
                <button type="button"
                        @click="groupOpen = !groupOpen; localStorage.setItem('planner.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}, groupOpen)"
                        class="flex items-center gap-1.5 pt-2 pb-1 px-2 w-full text-left group">
                    <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)]"
                          :class="groupOpen ? 'rotate-90' : ''">
                        @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
                    </span>
                    <span class="text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold group-hover:text-[var(--ui-secondary)] transition-colors">
                        {{ $typeGroup['type_name'] }}
                    </span>
                </button>
                <div x-show="groupOpen" x-collapse class="flex flex-col gap-0.5">
                    @foreach($typeGroup['children'] as $child)
                        @include('planner::livewire.partials.sidebar-entity-node', [
                            'node' => $child,
                            'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
