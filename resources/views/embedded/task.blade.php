@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        @livewire(\Platform\Planner\Livewire\Embedded\Task::class, ['plannerTask' => $plannerTask])
    </div>
@endsection


