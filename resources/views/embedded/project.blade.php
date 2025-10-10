@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        {{-- Embedded: embedded Projekt-Komponente rendern --}}
        @livewire('planner.embedded.project', ['plannerProject' => $plannerProject])
    </div>
@endsection


