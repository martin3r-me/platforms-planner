<x-ui-page>
    @include('planner::partials.planner-tokens')

    <x-slot name="navbar">
        <x-ui-page-navbar title="Präsentation" icon="heroicon-o-presentation-chart-line" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => 'Präsentation'],
        ]" />
    </x-slot>

    @php
        $fmtHours = fn ($min) => number_format(((int) $min) / 60, 1, ',', '.');
    @endphp

    {{-- ════════════════════════════════════════════════════════════
         ZUSTAND A · Engagement-Auswahl (der Kunde)
    ════════════════════════════════════════════════════════════ --}}
    @if(! $engagementId)
        <div class="flex-1 flex flex-col bg-[var(--ui-muted-5)] min-h-0 overflow-y-auto">
            <div class="max-w-3xl w-full mx-auto px-6 py-10">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)] m-0 mb-1">Mit welchem Kunden gehst du durch?</h1>
                    <p class="text-sm text-[var(--ui-muted)] m-0">
                        Wähle ein Engagement — du bekommst dann dessen laufende Projekte als ruhige, durchklickbare Slides.
                    </p>
                </div>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Engagement suchen …"
                    class="w-full text-sm rounded-lg border border-[var(--ui-border)]/60 px-3 py-2.5 mb-4 focus:border-[var(--planner-status-active)] focus:ring-1 focus:ring-[var(--planner-status-active)]/40 outline-none"
                    autofocus
                />

                <div class="space-y-2">
                    @forelse($this->engagements as $e)
                        <button
                            type="button"
                            wire:click="selectEngagement({{ $e['id'] }})"
                            class="w-full flex items-center gap-3 rounded-xl border border-[var(--ui-border)]/50 bg-white px-4 py-3 shadow-sm hover:border-[var(--planner-status-active)]/60 hover:shadow transition-all text-left group"
                        >
                            <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-700 inline-flex items-center justify-center flex-shrink-0">
                                @svg('heroicon-o-briefcase', 'w-5 h-5')
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-semibold text-[var(--ui-secondary)] truncate">{{ $e['name'] }}</span>
                                <span class="block text-[11px] text-[var(--ui-muted)]">
                                    {{ $e['count'] }} {{ $e['count'] === 1 ? 'laufendes Projekt' : 'laufende Projekte' }}
                                </span>
                            </span>
                            @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--planner-status-active)] flex-shrink-0')
                        </button>
                    @empty
                        <div class="p-10 text-center rounded-xl border border-dashed border-[var(--ui-border)]/60 bg-white">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--ui-muted-5)] mb-3">
                                @svg('heroicon-o-briefcase', 'w-6 h-6 text-[var(--ui-muted)]')
                            </div>
                            <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Keine Engagements mit laufenden Projekten</h3>
                            <p class="text-sm text-[var(--ui-muted)] m-0">
                                {{ $search !== '' ? 'Keine Treffer — Suche anpassen.' : 'Sobald Projekte an einem Engagement hängen, erscheinen sie hier.' }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    {{-- ════════════════════════════════════════════════════════════
         ZUSTAND B · Präsentation (durchklickbare Projekt-Slides)
    ════════════════════════════════════════════════════════════ --}}
    @else
        @php
            $slides = $this->slides;
            $total = count($slides);
            $current = $slides[$index] ?? null;
        @endphp

        <div
            class="flex-1 flex flex-col bg-[var(--ui-muted-5)] min-h-0"
            x-data
            @keydown.arrow-right.window="$wire.next()"
            @keydown.arrow-left.window="$wire.prev()"
        >
            {{-- Kopf: Kunde + Fortschritt + Verlassen --}}
            <div class="flex items-center gap-3 border-b border-[var(--ui-border)]/40 bg-white px-6 py-3 flex-shrink-0">
                <button
                    wire:click="exitPresentation"
                    class="inline-flex items-center gap-1 text-[12px] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]"
                    title="Kunde wechseln"
                >
                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                    Kunde wechseln
                </button>

                <div class="flex items-center gap-2 mx-auto">
                    @svg('heroicon-o-briefcase', 'w-4 h-4 text-indigo-600')
                    <span class="font-semibold text-[var(--ui-secondary)]">{{ $this->engagementName ?? 'Engagement' }}</span>
                </div>

                @if($total > 0)
                    <span class="text-[12px] text-[var(--ui-muted)] tabular-nums">Projekt {{ $index + 1 }} / {{ $total }}</span>
                @endif
            </div>

            @if(! $current)
                {{-- Engagement ohne laufende Projekte --}}
                <div class="flex-1 flex items-center justify-center p-12">
                    <div class="text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[var(--ui-muted-5)] mb-3">
                            @svg('heroicon-o-folder-open', 'w-7 h-7 text-[var(--ui-muted)]')
                        </div>
                        <h3 class="text-base font-semibold text-[var(--ui-secondary)] m-0 mb-1">Keine laufenden Projekte</h3>
                        <p class="text-sm text-[var(--ui-muted)] m-0">Für dieses Engagement gibt es gerade nichts durchzusprechen.</p>
                    </div>
                </div>
            @else
                {{-- ── Slide ── --}}
                <div class="flex-1 overflow-y-auto" wire:key="slide-{{ $current['id'] }}">
                    <div class="max-w-4xl w-full mx-auto px-8 py-8">

                        {{-- Titel --}}
                        <div class="mb-6">
                            <h1 class="text-3xl font-bold text-[var(--ui-secondary)] m-0 leading-tight">{{ $current['name'] }}</h1>
                            @if($current['owner_name'])
                                <p class="text-sm text-[var(--ui-muted)] m-0 mt-1">Verantwortlich: {{ $current['owner_name'] }}</p>
                            @endif
                        </div>

                        {{-- Canvas-Essenz --}}
                        @if(! empty($current['canvas']))
                            <div class="grid sm:grid-cols-3 gap-3 mb-6">
                                @foreach($current['canvas'] as $block)
                                    <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white p-4 shadow-sm">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2">{{ $block['label'] }}</div>
                                        <ul class="space-y-1.5 m-0 p-0 list-none">
                                            @foreach($block['entries'] as $entry)
                                                <li>
                                                    @if($entry['title'])
                                                        <span class="block text-[13px] font-medium text-[var(--ui-secondary)] leading-snug">{{ $entry['title'] }}</span>
                                                    @endif
                                                    @if($entry['content'])
                                                        <span class="block text-[12px] text-[var(--ui-muted)] leading-snug">{{ $entry['content'] }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Zeit: geplant vs. investiert --}}
                        @php
                            $planned = $current['planned_minutes'];
                            $logged = $current['logged_minutes'];
                            $pct = $planned > 0 ? min(100, round($logged / $planned * 100)) : null;
                            $over = $planned > 0 && $logged > $planned;
                        @endphp
                        <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white p-4 shadow-sm mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                    @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                    Zeit
                                </span>
                                <span class="text-[13px] tabular-nums text-[var(--ui-secondary)]">
                                    <span class="font-semibold">{{ $fmtHours($logged) }} h</span> investiert
                                    @if($planned > 0)
                                        <span class="text-[var(--ui-muted)]">von {{ $fmtHours($planned) }} h geplant</span>
                                    @endif
                                </span>
                            </div>
                            @if($pct !== null)
                                <div class="h-2.5 rounded-full bg-[var(--ui-muted-10)] overflow-hidden">
                                    <div class="h-full rounded-full {{ $over ? 'bg-amber-500' : 'bg-[var(--planner-status-active)]' }}" style="width: {{ $pct }}%"></div>
                                </div>
                                @if($over)
                                    <p class="text-[11px] text-amber-600 m-0 mt-1.5">Über der geplanten Zeit.</p>
                                @endif
                            @else
                                <p class="text-[11px] text-[var(--ui-muted)] m-0">Keine Planung hinterlegt.</p>
                            @endif
                        </div>

                        {{-- Offene Punkte / DoDs --}}
                        <div class="rounded-xl border border-[var(--ui-border)]/40 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                    @svg('heroicon-o-check-circle', 'w-3.5 h-3.5')
                                    Offene Punkte
                                </span>
                                @if($current['dod_total'] > 0)
                                    <span class="text-[12px] tabular-nums text-[var(--ui-muted)]">
                                        {{ $current['dod_checked'] }} / {{ $current['dod_total'] }} Kriterien erfüllt
                                    </span>
                                @endif
                            </div>

                            @if(count($current['tasks']) > 0)
                                <ul class="space-y-3 m-0 p-0 list-none">
                                    @foreach($current['tasks'] as $task)
                                        <li>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[14px] text-[var(--ui-secondary)]">{{ $task['title'] }}</span>
                                                @if($task['total'] > 0)
                                                    <span class="text-[11px] tabular-nums text-[var(--ui-muted)] bg-[var(--ui-muted-5)] rounded-full px-2 py-0.5">
                                                        {{ $task['checked'] }}/{{ $task['total'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if(! empty($task['open_items']))
                                                <ul class="mt-1.5 ml-1 space-y-1 m-0 p-0 list-none">
                                                    @foreach($task['open_items'] as $item)
                                                        <li class="flex items-start gap-2 text-[13px] text-[var(--ui-secondary)]">
                                                            <span class="mt-0.5 w-4 h-4 rounded border border-[var(--ui-border)] flex-shrink-0"></span>
                                                            <span class="leading-snug">{{ $item }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-[13px] text-[var(--ui-muted)] m-0">Keine offenen Aufgaben — alles erledigt. 🎉</p>
                            @endif
                        </div>

                    </div>
                </div>

                {{-- ── Navigations-Leiste ── --}}
                <div class="flex items-center gap-3 border-t border-[var(--ui-border)]/40 bg-white px-6 py-3 flex-shrink-0">
                    <button
                        wire:click="prev"
                        @disabled($index <= 0)
                        class="inline-flex items-center gap-1 rounded-lg border border-[var(--ui-border)]/60 px-3 py-1.5 text-[13px] font-medium text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        @svg('heroicon-o-chevron-left', 'w-4 h-4')
                        Zurück
                    </button>

                    {{-- Punkte-Navigation --}}
                    <div class="flex items-center gap-1.5 mx-auto flex-wrap justify-center">
                        @foreach($slides as $i => $s)
                            <button
                                wire:click="goTo({{ $i }})"
                                class="w-2.5 h-2.5 rounded-full transition-all {{ $i === $index ? 'bg-[var(--planner-status-active)] scale-125' : 'bg-[var(--ui-muted-10)] hover:bg-[var(--ui-muted)]' }}"
                                title="{{ $s['name'] }}"
                            ></button>
                        @endforeach
                    </div>

                    <button
                        wire:click="next"
                        @disabled($index >= $total - 1)
                        class="inline-flex items-center gap-1 rounded-lg bg-[var(--planner-status-active)] text-white px-3 py-1.5 text-[13px] font-medium hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Weiter
                        @svg('heroicon-o-chevron-right', 'w-4 h-4')
                    </button>
                </div>
            @endif
        </div>
    @endif

</x-ui-page>
