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
        {{-- Kind-Entities nach Typ gruppiert (rekursiv) --}}
        @foreach($node['children_by_type'] as $typeGroup)
            @if($node['children_by_type']->count() > 1 || $node['projects']->isNotEmpty())
                <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}"
                     class="pt-1.5 pb-0.5 px-2 text-[10px] uppercase tracking-wider text-[var(--ui-muted)] font-semibold">
                    {{ $typeGroup['type_name'] }}
                </div>
            @endif
            @foreach($typeGroup['children'] as $child)
                @include('planner::livewire.partials.sidebar-entity-node', [
                    'node' => $child,
                    'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                ])
            @endforeach
        @endforeach
        {{-- Eigene Projekte --}}
        @foreach($node['projects'] as $project)
            <x-ui-sidebar-item wire:key="entity-{{ $node['entity_id'] }}-project-{{ $project->id }}" :href="route('planner.projects.show', ['plannerProject' => $project])" :title="$project->name">
                @svg('heroicon-o-folder', 'w-5 h-5 flex-shrink-0 text-[var(--ui-secondary)]')
                <div class="flex-1 min-w-0 ml-2 flex items-center gap-1.5">
                    <span class="truncate text-sm font-medium">{{ $project->name }}</span>
                    @if($project->color)
                        <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $project->color }}"></span>
                    @endif
                </div>
            </x-ui-sidebar-item>
        @endforeach
    </div>
</div>
