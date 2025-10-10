@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        {{-- Minimal: Listen-Ansicht, Karten verlinken zur Embedded-Task-Route --}}
        <x-ui-page>
            <x-slot name="navbar">
                <x-ui-page-navbar :title="$project->name" icon="heroicon-o-clipboard-document-list" />
            </x-slot>

            <x-ui-page-container spacing="space-y-4">
                <x-ui-kanban-container sortable="updateTaskGroupOrder" sortable-group="updateTaskOrder">
                    @foreach($groups->filter(fn ($g) => !($g->isBacklog ?? false) && !($g->isDoneGroup ?? false)) as $column)
                        <x-ui-kanban-column :title="($column->label ?? $column->name ?? 'Spalte')" :sortable-id="$column->id" :scrollable="true">
                            @foreach($column->tasks as $task)
                                <x-ui-kanban-card :title="$task->title" :sortable-id="$task->id" :href="route('planner.embedded.task', $task)">
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        @if($task->due_date)
                                            Fällig: {{ $task->due_date->format('d.m.Y') }}
                                        @else
                                            Keine Fälligkeit
                                        @endif
                                    </div>
                                </x-ui-kanban-card>
                            @endforeach
                        </x-ui-kanban-column>
                    @endforeach
                </x-ui-kanban-container>
            </x-ui-page-container>
        </x-ui-page>
    </div>
@endsection


