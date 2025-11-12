<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Übersicht" icon="heroicon-o-calendar" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true">
            <div class="p-4 space-y-4">
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        @php 
                            $stats = [
                                ['title' => 'Anstehende Aufgaben', 'count' => $tasks->count(), 'icon' => 'clock', 'variant' => 'warning'],
                                ['title' => 'Mit Fälligkeitsdatum', 'count' => $tasks->filter(fn($t) => $t->due_date)->count(), 'icon' => 'calendar', 'variant' => 'info'],
                            ];
                        @endphp
                        @foreach($stats as $stat)
                            <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-' . $stat['icon'], 'w-4 h-4 text-[var(--ui-' . $stat['variant'] . ')]')
                                    <span class="text-sm text-[var(--ui-secondary)]">{{ $stat['title'] }}</span>
                                </div>
                                <span class="text-sm font-semibold text-[var(--ui-' . $stat['variant'] . ')]">
                                    {{ $stat['count'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                <div class="space-y-3 text-sm">
                    <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="text-[var(--ui-muted)] text-xs">Aktivitäten werden hier angezeigt</div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Anstehende Aufgaben</h2>

            <div class="lg:grid lg:grid-cols-12 lg:gap-x-16">
                {{-- Aufgabenliste (links) --}}
                <ol class="mt-4 divide-y divide-gray-100 text-sm/6 lg:col-span-7 xl:col-span-8 dark:divide-white/10">
                    @forelse($tasks as $task)
                        <li class="relative flex gap-x-6 py-6 xl:static">
                            @if($task->userInCharge && $task->userInCharge->avatar_url)
                                <img src="{{ $task->userInCharge->avatar_url }}" alt="" class="size-14 flex-none rounded-full dark:outline dark:-outline-offset-1 dark:outline-white/10" />
                            @else
                                <div class="size-14 flex-none rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <span class="text-gray-500 dark:text-gray-400 text-lg font-medium">
                                        {{ strtoupper(substr($task->userInCharge->name ?? '?', 0, 1)) }}
                                    </span>
                                </div>
                            @endif
                            <div class="flex-auto">
                                <h3 class="pr-10 font-semibold text-gray-900 xl:pr-0 dark:text-white">
                                    <a href="{{ route('planner.tasks.show', $task) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                        {{ $task->title }}
                                    </a>
                                </h3>
                                <dl class="mt-2 flex flex-col text-gray-500 xl:flex-row dark:text-gray-400">
                                    <div class="flex items-start gap-x-3">
                                        <dt class="mt-0.5">
                                            <span class="sr-only">Datum</span>
                                            <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5 text-gray-400 dark:text-gray-500">
                                                <path d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" fill-rule="evenodd" />
                                            </svg>
                                        </dt>
                                        <dd>
                                            <time datetime="{{ $task->due_date->toIso8601String() }}">
                                                {{ $task->due_date->locale('de')->isoFormat('D. MMMM YYYY [um] HH:mm [Uhr]') }}
                                            </time>
                                        </dd>
                                    </div>
                                    @if($task->project)
                                        <div class="mt-2 flex items-start gap-x-3 xl:mt-0 xl:ml-3.5 xl:border-l xl:border-gray-400/50 xl:pl-3.5 dark:xl:border-gray-500/50">
                                            <dt class="mt-0.5">
                                                <span class="sr-only">Projekt</span>
                                                <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5 text-gray-400 dark:text-gray-500">
                                                    <path d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" clip-rule="evenodd" fill-rule="evenodd" />
                                                </svg>
                                            </dt>
                                            <dd>
                                                <a href="{{ route('planner.projects.show', $task->project) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                    {{ $task->project->name }}
                                                </a>
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        </li>
                    @empty
                        <li class="py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <p class="text-sm">Keine anstehenden Aufgaben gefunden.</p>
                            </div>
                        </li>
                    @endforelse
                </ol>

                {{-- Kalender (rechts) --}}
                <div class="mt-10 text-center lg:col-start-8 lg:col-end-13 lg:row-start-1 lg:mt-9 xl:col-start-9">
                    <div class="flex items-center text-gray-900 dark:text-white">
                        <button 
                            type="button" 
                            wire:click="previousMonth"
                            class="-m-1.5 flex flex-none items-center justify-center p-1.5 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-white"
                        >
                            <span class="sr-only">Vorheriger Monat</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5">
                                <path d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" fill-rule="evenodd" />
                            </svg>
                        </button>
                        <div class="flex-auto text-sm font-semibold">{{ ucfirst($calendarData['monthName']) }} {{ $calendarData['year'] }}</div>
                        <button 
                            type="button" 
                            wire:click="nextMonth"
                            class="-m-1.5 flex flex-none items-center justify-center p-1.5 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-white"
                        >
                            <span class="sr-only">Nächster Monat</span>
                            <svg viewBox="0 0 20 20" fill="currentColor" data-slot="icon" aria-hidden="true" class="size-5">
                                <path d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    {{-- Wochentage --}}
                    <div class="mt-6 grid grid-cols-7 text-xs/6 text-gray-500 dark:text-gray-400">
                        <div>M</div>
                        <div>D</div>
                        <div>M</div>
                        <div>D</div>
                        <div>F</div>
                        <div>S</div>
                        <div>S</div>
                    </div>

                    {{-- Kalender-Grid --}}
                    <div class="isolate mt-2 grid grid-cols-7 gap-px rounded-lg bg-gray-200 text-sm shadow-sm ring-1 ring-gray-200 dark:bg-white/15 dark:shadow-none dark:ring-white/15">
                        @foreach($calendarData['days'] as $index => $day)
                            @php
                                $isCurrentMonth = $day['isCurrentMonth'];
                                $isToday = $day['isToday'];
                                $isSelected = $day['isSelected'];
                                $dateStr = $day['date']->format('Y-m-d');
                                
                                // Button-Klassen basierend auf Zustand
                                $buttonClasses = ['py-1.5', 'focus:z-10'];
                                
                                // Rounded corners
                                if ($index === 0) {
                                    $buttonClasses[] = 'first:rounded-tl-lg';
                                }
                                if ($index === 41) {
                                    $buttonClasses[] = 'last:rounded-br-lg';
                                }
                                if ($index === 35) {
                                    $buttonClasses[] = 'rounded-bl-lg';
                                }
                                if ($index === 6) {
                                    $buttonClasses[] = 'rounded-tr-lg';
                                }
                                
                                // Hintergrund und Text basierend auf Zustand
                                if (!$isCurrentMonth) {
                                    $buttonClasses[] = 'bg-gray-50 dark:bg-gray-900/75';
                                    if (!$isSelected && !$isToday) {
                                        $buttonClasses[] = 'text-gray-400 dark:text-gray-500';
                                    }
                                } else {
                                    $buttonClasses[] = 'bg-white dark:bg-gray-900/90';
                                    if (!$isSelected && !$isToday) {
                                        $buttonClasses[] = 'text-gray-900 dark:text-white';
                                    }
                                }
                                
                                // Hover
                                $buttonClasses[] = 'hover:bg-gray-100 dark:hover:bg-gray-900/25';
                                if ($isCurrentMonth) {
                                    $buttonClasses[] = 'dark:hover:bg-gray-900/50';
                                }
                                
                                // Ausgewählt
                                if ($isSelected) {
                                    $buttonClasses[] = 'font-semibold text-white dark:text-gray-900';
                                }
                                
                                // Heute (aber nicht ausgewählt)
                                if ($isToday && !$isSelected) {
                                    $buttonClasses[] = 'font-semibold text-indigo-600 dark:text-indigo-400';
                                }
                                
                                // Time-Klassen
                                $timeClasses = [
                                    'mx-auto',
                                    'flex',
                                    'size-7',
                                    'items-center',
                                    'justify-center',
                                    'rounded-full',
                                ];
                                
                                if ($isSelected) {
                                    if ($isToday) {
                                        $timeClasses[] = 'bg-indigo-600 dark:bg-indigo-500';
                                    } else {
                                        $timeClasses[] = 'bg-gray-900 dark:bg-white';
                                    }
                                }
                            @endphp
                            <button 
                                type="button" 
                                wire:click="selectDate('{{ $dateStr }}')"
                                class="{{ implode(' ', $buttonClasses) }}"
                            >
                                <time datetime="{{ $dateStr }}" class="{{ implode(' ', $timeClasses) }}">
                                    {{ $day['date']->day }}
                                </time>
                            </button>
                        @endforeach
                    </div>

                    <a 
                        href="{{ route('planner.my-tasks') }}" 
                        wire:navigate
                        class="mt-8 w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-indigo-500 dark:shadow-none dark:hover:bg-indigo-400 dark:focus-visible:outline-indigo-500"
                    >
                        Aufgabe hinzufügen
                    </a>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
