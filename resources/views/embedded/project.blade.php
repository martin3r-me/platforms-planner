@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        {{-- Embedded: bestehende Projekt-Komponente rendern --}}
        @livewire('planner.project', ['plannerProject' => $plannerProject])
        <script>
            // Nach dem Rendern alle Task-Karten-Links auf embedded umbiegen
            document.addEventListener('DOMContentLoaded', function(){
                document.querySelectorAll('a[wire\\:navigate][href*="/planner/tasks/"]').forEach(function(a){
                    const id = a.getAttribute('href').split('/').pop();
                    a.removeAttribute('wire:navigate');
                    a.setAttribute('href', '/planner/embedded/planner/tasks/' + encodeURIComponent(id));
                });
            });
        </script>
    </div>
@endsection


