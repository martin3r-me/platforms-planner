@extends('platform::layouts.embedded')

@section('content')
    <div class="h-full">
        {{-- Embedded: eigenes Rendering, Karten verlinken zur Embedded-Task-Route, Board als Liste --}}
        @php
            $component = \Livewire\Livewire::mount('planner.project', ['plannerProject' => $plannerProject]);
            $view = $component->html();
        @endphp
        {!! $view !!}
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


