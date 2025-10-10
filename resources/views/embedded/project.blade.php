@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        @livewire('planner.project', ['plannerProject' => $plannerProject])
    </div>
@endsection


