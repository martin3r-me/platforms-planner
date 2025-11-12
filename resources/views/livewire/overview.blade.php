<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Übersicht" icon="heroicon-o-calendar" />
    </x-slot>

    <div class="lg:grid lg:grid-cols-12 lg:gap-x-16">
        {{-- Aufgabenliste (links) --}}
        <div class="mt-10 lg:col-span-7 xl:col-span-8">
            <h2 class="text-base font-semibold text-[var(--ui-secondary)] mb-6">
                @if($selectedDate)
                    Aufgaben am {{ $selectedDate->locale('de')->isoFormat('D. MMMM YYYY') }}
                @else
                    Anstehende Aufgaben
                @endif
            </h2>
            
            <ol class="divide-y divide-[var(--ui-border)]/60">
                @forelse($tasks as $task)
                    <li class="relative flex gap-x-6 py-6">
                        <div class="flex-auto">
                            <h3 class="pr-10 font-semibold text-[var(--ui-secondary)]">
                                <a href="{{ route('planner.tasks.show', $task) }}" wire:navigate class="hover:text-[var(--ui-primary)]">
                                    {{ $task->title }}
                                </a>
                            </h3>
                            <dl class="mt-2 flex flex-col text-[var(--ui-muted)] xl:flex-row">
                                <div class="flex items-start gap-x-3">
                                    <dt class="mt-0.5">
                                        <span class="sr-only">Datum</span>
                                        @svg('heroicon-o-calendar', 'w-5 h-5 text-[var(--ui-muted)]')
                                    </dt>
                                    <dd>
                                        <time datetime="{{ $task->due_date->toIso8601String() }}">
                                            {{ $task->due_date->locale('de')->isoFormat('D. MMMM YYYY [um] HH:mm [Uhr]') }}
                                        </time>
                                    </dd>
                                </div>
                                @if($task->project)
                                    <div class="mt-2 flex items-start gap-x-3 xl:mt-0 xl:ml-3.5 xl:border-l xl:border-[var(--ui-border)]/40 xl:pl-3.5">
                                        <dt class="mt-0.5">
                                            <span class="sr-only">Projekt</span>
                                            @svg('heroicon-o-folder', 'w-5 h-5 text-[var(--ui-muted)]')
                                        </dt>
                                        <dd>
                                            <a href="{{ route('planner.projects.show', $task->project) }}" wire:navigate class="hover:text-[var(--ui-primary)]">
                                                {{ $task->project->name }}
                                            </a>
                                        </dd>
                                    </div>
                                @endif
                                @if($task->userInCharge)
                                    <div class="mt-2 flex items-start gap-x-3 xl:mt-0 xl:ml-3.5 xl:border-l xl:border-[var(--ui-border)]/40 xl:pl-3.5">
                                        <dt class="mt-0.5">
                                            <span class="sr-only">Verantwortlich</span>
                                            @svg('heroicon-o-user', 'w-5 h-5 text-[var(--ui-muted)]')
                                        </dt>
                                        <dd>{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </li>
                @empty
                    <li class="py-12 text-center">
                        <div class="text-[var(--ui-muted)]">
                            <p class="text-sm">Keine anstehenden Aufgaben gefunden.</p>
                        </div>
                    </li>
                @endforelse
            </ol>
        </div>

        {{-- Kalender (rechts) --}}
        <div class="mt-10 text-center lg:col-start-8 lg:col-end-13 lg:row-start-1 lg:mt-9 xl:col-start-9">
            <div class="flex items-center justify-center text-[var(--ui-secondary)] mb-6">
                <button 
                    type="button" 
                    wire:click="previousMonth"
                    class="-m-1.5 flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                >
                    <span class="sr-only">Vorheriger Monat</span>
                    @svg('heroicon-o-chevron-left', 'w-5 h-5')
                </button>
                <div class="flex-auto text-sm font-semibold px-4">
                    {{ ucfirst($calendarData['monthName']) }} {{ $calendarData['year'] }}
                </div>
                <button 
                    type="button" 
                    wire:click="nextMonth"
                    class="-m-1.5 flex flex-none items-center justify-center p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                >
                    <span class="sr-only">Nächster Monat</span>
                    @svg('heroicon-o-chevron-right', 'w-5 h-5')
                </button>
            </div>

            {{-- Wochentage --}}
            <div class="mt-6 grid grid-cols-7 text-xs/6 text-[var(--ui-muted)]">
                <div>M</div>
                <div>D</div>
                <div>M</div>
                <div>D</div>
                <div>F</div>
                <div>S</div>
                <div>S</div>
            </div>

            {{-- Kalender-Grid --}}
            <div class="isolate mt-2 grid grid-cols-7 gap-px rounded-lg bg-[var(--ui-border)]/60 text-sm shadow-sm ring-1 ring-[var(--ui-border)]/60">
                @foreach($calendarData['days'] as $day)
                    @php
                        $isCurrentMonth = $day['isCurrentMonth'];
                        $isToday = $day['isToday'];
                        $isSelected = $day['isSelected'];
                        $dateStr = $day['date']->format('Y-m-d');
                        
                        // Klassen für den Button
                        $buttonClasses = [
                            'py-1.5',
                            'first:rounded-tl-lg',
                            'last:rounded-br-lg',
                            'hover:bg-[var(--ui-muted-5)]',
                            'focus:z-10',
                            'transition-colors',
                        ];
                        
                        if (!$isCurrentMonth) {
                            $buttonClasses[] = 'bg-[var(--ui-surface)]';
                            $buttonClasses[] = 'text-[var(--ui-muted)]';
                        } else {
                            $buttonClasses[] = 'bg-white';
                            if (!$isSelected && !$isToday) {
                                $buttonClasses[] = 'text-[var(--ui-secondary)]';
                            }
                        }
                        
                        if ($isSelected) {
                            $buttonClasses[] = 'font-semibold';
                            $buttonClasses[] = 'text-white';
                        }
                        
                        if ($isToday && !$isSelected) {
                            $buttonClasses[] = 'font-semibold';
                            $buttonClasses[] = 'text-[var(--ui-primary)]';
                        }
                        
                        // Klassen für den Time-Tag
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
                                $timeClasses[] = 'bg-[var(--ui-primary)]';
                            } else {
                                $timeClasses[] = 'bg-[var(--ui-secondary)]';
                            }
                        } elseif ($isToday) {
                            $timeClasses[] = 'ring-2';
                            $timeClasses[] = 'ring-[var(--ui-primary)]';
                        }
                    @endphp
                    <button 
                        type="button" 
                        wire:click="selectDate('{{ $dateStr }}')"
                        class="{{ implode(' ', $buttonClasses) }} relative"
                    >
                        <time datetime="{{ $dateStr }}" class="{{ implode(' ', $timeClasses) }}">
                            {{ $day['date']->day }}
                        </time>
                        @if($day['taskCount'] > 0 && $day['isCurrentMonth'])
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-[var(--ui-primary)] text-[10px] font-semibold text-white">
                                {{ $day['taskCount'] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <a 
                href="{{ route('planner.my-tasks') }}" 
                wire:navigate
                class="mt-8 w-full rounded-md bg-[var(--ui-primary)] px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[var(--ui-primary)]/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--ui-primary)] transition-colors inline-flex items-center justify-center"
            >
                Aufgabe hinzufügen
            </a>
        </div>
    </div>
</x-ui-page>

