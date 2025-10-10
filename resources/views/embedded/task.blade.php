@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        @livewire('planner.embedded.task', ['plannerTask' => $plannerTask])
    </div>
@endsection


