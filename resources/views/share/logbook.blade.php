{{-- Kunden-Tor · Logbuch eines Projekts: Entscheidungen, Meilensteine und Risiken mit ihrem Warum, neueste zuerst.
     Daten: $entries (PlannerShareView::logbook). --}}
@php
    $tone = ['decision' => 'var(--nx-info)', 'milestone' => 'var(--nx-success)', 'risk' => 'var(--nx-warning)'];
@endphp
<div class="gp-wrap py-6">
    @foreach (collect($entries)->groupBy(fn ($e) => $e['date']->copy()->locale('de')->isoFormat('MMMM YYYY')) as $month => $monthEntries)
        <section @class(['mt-8' => ! $loop->first])>
            <h3 class="mb-2 text-sm font-semibold">{{ $month }}</h3>
            <div class="max-w-5xl overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($monthEntries as $e)
                    <div class="flex items-start gap-3 px-4 py-3">
                        <span class="mt-0.5 shrink-0" style="color: {{ $tone[$e['type']] ?? 'var(--nx-faint)' }}">@svg($e['icon'], 'w-4 h-4')</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-sm font-medium leading-snug">{{ $e['title'] }}</span>
                                <span class="text-[11px] font-medium uppercase tracking-wide" style="color: {{ $tone[$e['type']] ?? 'var(--nx-faint)' }}">{{ $e['type_label'] }}</span>
                            </div>
                            @if ($e['rationale'])
                                <p class="mt-1 whitespace-pre-line text-[13px] leading-relaxed text-[color:var(--nx-muted)]">{{ $e['rationale'] }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 pt-0.5 text-right text-xs text-[color:var(--nx-faint)]">
                            {{ $e['date']->copy()->locale('de')->isoFormat('D. MMM') }}
                            @if ($e['person'])<span class="block">{{ $e['person'] }}</span>@endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
