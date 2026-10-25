{{-- Kunden-Tor · Verlauf eines Projekts: Erledigtes nach Kalenderwochen. Daten: $weeks (PlannerShareView::historyWeeks). --}}
<div class="mx-auto max-w-[1200px] px-4 py-6 sm:px-8">
    @forelse ($weeks as $week)
        <section @class(['mt-8' => ! $loop->first])>
            <div class="mb-2 flex items-baseline justify-between gap-3">
                <h3 class="text-sm font-semibold">{{ $week['label'] }}</h3>
                <span class="text-xs text-[color:var(--nx-faint)]">{{ count($week['tasks']) }} erledigt</span>
            </div>
            <div class="max-w-3xl overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($week['tasks'] as $t)
                    <div class="flex items-start gap-3 px-4 py-2.5">
                        @svg('heroicon-s-check-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-success)]')
                        <span class="min-w-0 flex-1 text-sm leading-snug">{{ $t['title'] }}</span>
                        <span class="shrink-0 pt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $t['at'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="max-w-3xl rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-10 text-center text-sm text-[color:var(--nx-faint)]">Noch nichts erledigt – sobald es losgeht, sehen Sie hier jeden Schritt.</div>
    @endforelse
</div>
