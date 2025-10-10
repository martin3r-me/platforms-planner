@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        {{-- DEBUG: Test ob embedded Komponente geladen wird --}}
        <div class="bg-red-100 p-4 mb-4">
            <p class="text-red-800">DEBUG: Embedded Project View geladen</p>
            <p class="text-red-800">Projekt: {{ $plannerProject->name }}</p>
            <p class="text-red-800">Zeit: {{ now() }}</p>
        </div>
        
        {{-- Embedded: embedded Projekt-Komponente rendern --}}
        @livewire('planner.embedded.project', ['plannerProject' => $plannerProject])
    </div>
@endsection


