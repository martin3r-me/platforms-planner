@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        <x-ui-page>
            <x-slot name="navbar">
                <x-ui-page-navbar :title="$task->title" icon="heroicon-o-clipboard-document-check">
                    @if($task->project)
                        <a href="{{ route('planner.embedded.project', ['plannerProject' => $task->project->id]) }}" class="text-sm underline text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] mr-2">
                            Zurück zum Projekt
                        </a>
                    @endif
                </x-ui-page-navbar>
            </x-slot>

            @include('planner::livewire.task-inner')
        </x-ui-page>
    </div>
@endsection


