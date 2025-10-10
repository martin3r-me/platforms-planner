@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        @livewire('planner.task', ['plannerTask' => $plannerTask])
    </div>
@endsection


