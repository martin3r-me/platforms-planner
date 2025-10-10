@extends('platform::layouts.embedded')

@section('content')
    <div class="min-h-screen">
        @livewire('planner.project', ['plannerProject' => $plannerProject])
    </div>
@endsection


