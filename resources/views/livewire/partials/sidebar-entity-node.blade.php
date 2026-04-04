{{-- Rekursiver Entity-Knoten für Sidebar-Baum --}}
@props(['node', 'typeIcon' => null, 'depth' => 0])

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('planner.entity.' + {{ $node['entity_id'] }}) === 'true' }"
     class="flex flex-col">
    {{-- Entity-Zeile --}}
    <button type="button"
            @click="open = !open; localStorage.setItem('planner.entity.' + {{ $node['entity_id'] }}, open)"
            class="flex items-center gap-1 py-1 px-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition w-full text-left group">
        <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)]"
              :class="open ? 'rotate-90' : ''">
            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
        </span>
        <span class="truncate text-xs font-medium">{{ $node['entity_name'] }}</span>
        <span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $node['total_projects'] }}</span>
    </button>

    {{-- Aufgeklappter Inhalt --}}
    <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[var(--ui-border)]">
        {{-- 1. Eigene Projekte --}}
        @foreach($node['projects'] as $project)
            <a wire:key="entity-{{ $node['entity_id'] }}-project-{{ $project->id }}"
               href="{{ route('planner.projects.show', ['plannerProject' => $project]) }}"
               wire:navigate
               title="{{ $project->name }}"
               class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                <span class="w-1 h-1 rounded-full flex-shrink-0 bg-[var(--ui-muted)] opacity-40"></span>
                <span class="truncate text-[11px]">{{ $project->name }}</span>
                @if($project->color)
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ml-auto" style="background-color: {{ $project->color }}"></span>
                @endif
            </a>
        @endforeach

        {{-- 2. Kind-Entities nach Typ gruppiert --}}
        @foreach($node['children_by_type'] as $typeGroup)
            <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}"
                 x-data="{ groupOpen: localStorage.getItem('planner.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}) !== 'false' }"
                 class="flex flex-col">
                {{-- Typ-Label (nur wenn mehrere Gruppen oder eigene Projekte) --}}
                @if($node['children_by_type']->count() > 1 || $node['projects']->isNotEmpty())
                    <button type="button"
                            @click="groupOpen = !groupOpen; localStorage.setItem('planner.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}, groupOpen)"
                            class="flex items-center gap-1 mt-1 mb-0.5 pl-2.5 pr-2 w-full text-left group cursor-pointer">
                        <span class="w-2.5 h-2.5 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)] opacity-50"
                              :class="groupOpen ? 'rotate-90' : ''">
                            @svg('heroicon-o-chevron-right', 'w-2 h-2')
                        </span>
                        <span class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] opacity-60 group-hover:opacity-100 transition-opacity">
                            {{ $typeGroup['type_name'] }}
                        </span>
                    </button>
                @endif
                <div x-show="groupOpen" x-collapse class="flex flex-col">
                    @foreach($typeGroup['children'] as $child)
                        @include('planner::livewire.partials.sidebar-entity-node', [
                            'node' => $child,
                            'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
