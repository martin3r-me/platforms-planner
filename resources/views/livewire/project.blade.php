@extends('platform::layouts.app')

@section('secondary-navbar')
    <x-ui-button wire:click="createTask">+ Aufgabe</x-ui-button>
    <x-ui-button wire:click="createSprintSlot">+ Spalte</x-ui-button>
    {{-- ...weitere Tools, Filter, etc... --}}
@endsection

{{-- Hauptinhalt: --}}
<div class="h-full">
    <x-ui-kanban-board wire:sortable="updateTaskGroupOrder" wire:sortable-group="updateTaskOrder">

        {{-- BACKLOG --}}
        <x-ui-kanban-column :title="'BACKLOG'">
            <x-slot name="extra">
                <div class="d-flex gap-1">
                    @can('update', $project)
                        <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTask()">+ Aufgabe</x-ui-button>
                        <x-ui-button variant="primary-outline" size="sm" class="w-full" wire:click="createSprintSlot">+ Spalte</x-ui-button>
                    @endcan
                    <x-ui-button variant="info-outline" size="sm" class="w-full" @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })">Info</x-ui-button>
                </div>
            </x-slot>

            @foreach ($groups->first()->tasks as $task)
                <livewire:planner.task-preview-card 
                    :task="$task"
                    wire:key="task-preview-{{ $task->uuid }}"
                />
            @endforeach
        </x-ui-kanban-column>

        {{-- Mittlere Spalten --}}
        @foreach($groups->filter(fn ($g) => !($g->isBacklog || ($g->isDoneGroup ?? false))) as $column)
            <x-ui-kanban-column
                :title="$column->label"
                :sortable-id="$column->id">

                <x-slot name="extra">
                    <div class="d-flex gap-1">
                        @can('update', $project)
                            <x-ui-button variant="success-outline" size="sm" class="w-full" wire:click="createTask('{{ $column->id }}')">
                                + Neue Aufgabe
                            </x-ui-button>
                            <x-ui-button variant="primary-outline" size="sm" class="w-full" @click="$dispatch('open-modal-sprint-slot-settings', { sprintSlotId: {{ $column->id }} })">Settings</x-ui-button>
                        @endcan
                    </div>
                </x-slot>

                @foreach($column->tasks as $task)
                    <livewire:planner.task-preview-card 
                        :task="$task"
                        wire:key="task-preview-{{ $task->uuid }}"
                    />
                @endforeach

            </x-ui-kanban-column>
        @endforeach

        {{-- Erledigt --}}
        @php $completedGroup = $groups->last(); @endphp
        @if ($completedGroup->isDoneGroup ?? false)
            <x-ui-kanban-column :title="'Erledigt'">
                @forelse ($completedGroup->tasks as $task)
                    <livewire:planner.task-preview-card 
                        :task="$task"
                        wire:key="task-preview-{{ $task->uuid }}"
                    />
                @empty
                    <div class="text-xs text-slate-400 italic">Noch keine erledigten Aufgaben</div>
                @endforelse
            </x-ui-kanban-column>
        @endif 
    </x-ui-kanban-board>

    <livewire:planner.project-settings-modal/>
    <livewire:planner.sprint-slot-settings-modal/>
</div>