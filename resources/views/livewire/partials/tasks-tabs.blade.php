{{-- Gemeinsame Tab-Leiste der Aufgaben-Sichten (my-tasks · delegated · completed).
     Eine Sidebar-Sektion „Aufgaben" → hier wird zwischen den Sichten gewechselt. --}}
@php
    $taskTabs = [
        ['route' => 'planner.my-tasks',        'label' => 'Meine Aufgaben', 'icon' => 'heroicon-o-clipboard-document-check'],
        ['route' => 'planner.delegated-tasks', 'label' => 'Delegiert',      'icon' => 'heroicon-o-user-group'],
        ['route' => 'planner.completed-tasks', 'label' => 'Erledigt',       'icon' => 'heroicon-o-check-circle'],
    ];
@endphp
<div class="inline-flex rounded-md border border-[color:var(--nx-line-strong)] overflow-hidden">
    @foreach($taskTabs as $i => $tab)
        @php $active = request()->routeIs($tab['route']); @endphp
        <a href="{{ route($tab['route']) }}" wire:navigate
           title="{{ $tab['label'] }}"
           class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium transition-colors {{ $i > 0 ? 'border-l border-[color:var(--nx-line-strong)]' : '' }} {{ $active ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
            @svg($tab['icon'], 'w-3.5 h-3.5')
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
