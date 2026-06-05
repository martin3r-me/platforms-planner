@php
    $plannedHours = $project->totalPlannedMinutes() > 0 ? $project->totalPlannedMinutes() / 60 : null;
    $billingLabel = $project->billing_method
        ? (is_object($project->billing_method) ? ($project->billing_method->value ?? null) : $project->billing_method)
        : null;
    $canSettings = auth()->user()?->can('settings', $project) ?? false;
@endphp

<div class="flex flex-col h-full">

    {{-- Board-only: Done-Toggle (Step 6 wird das durch eine collapsed Spalte ersetzen) --}}
    @if($activeTab === 'board')
        <div class="px-4 pt-4">
            <button
                type="button"
                wire:click="toggleShowDoneColumn"
                class="w-full inline-flex items-center justify-between gap-2 h-8 px-2.5 rounded border border-[var(--ui-border)]/60 text-[11px] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
            >
                <span class="inline-flex items-center gap-1.5">
                    @if($showDoneColumn)
                        @svg('heroicon-o-eye-slash', 'w-3.5 h-3.5 opacity-60')
                        <span>Erledigte ausblenden</span>
                    @else
                        @svg('heroicon-o-eye', 'w-3.5 h-3.5 opacity-60')
                        <span>Erledigte anzeigen</span>
                    @endif
                </span>
                @if($headerDoneCount > 0)
                    <span class="tabular-nums text-[var(--ui-muted)]">{{ $headerDoneCount }}</span>
                @endif
            </button>
        </div>
    @endif

    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">

        {{-- ÜBER DAS PROJEKT --}}
        <section>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0">Über das Projekt</h3>
                @if($canSettings)
                    <button
                        type="button"
                        @click="$dispatch('open-modal-project-settings', { projectId: {{ $project->id }} })"
                        class="text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors"
                        title="Bearbeiten"
                    >
                        @svg('heroicon-o-pencil-square', 'w-3 h-3')
                    </button>
                @endif
            </div>
            <dl class="space-y-1.5 text-[11px]">
                @if($project->project_type)
                    <div class="flex items-baseline gap-3">
                        <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Typ</dt>
                        <dd class="text-[var(--ui-secondary)] m-0 truncate">{{ $project->project_type?->value }}</dd>
                    </div>
                @endif
                <div class="flex items-baseline gap-3">
                    <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Start</dt>
                    <dd class="text-[var(--ui-secondary)] m-0">{{ $project->created_at->format('d.m.Y') }}</dd>
                </div>
                @if($plannedHours)
                    <div class="flex items-baseline gap-3">
                        <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Geplant</dt>
                        <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">{{ number_format($plannedHours, 1, ',', '.') }} h</dd>
                    </div>
                @endif
                @if($billingLabel)
                    <div class="flex items-baseline gap-3">
                        <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Abrechnung</dt>
                        <dd class="text-[var(--ui-secondary)] m-0 truncate">{{ $billingLabel }}</dd>
                    </div>
                @endif
                @if($project->budget_amount)
                    <div class="flex items-baseline gap-3">
                        <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Budget</dt>
                        <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">
                            {{ number_format((float) $project->budget_amount, 0, ',', '.') }} {{ $project->currency ?? 'EUR' }}
                        </dd>
                    </div>
                @endif
                @if($project->customer_cost_center ?? null)
                    <div class="flex items-baseline gap-3">
                        <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Kostenst.</dt>
                        <dd class="text-[var(--ui-secondary)] m-0 truncate">{{ $project->customer_cost_center }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- DEIN BEZUG --}}
        <section>
            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0 mb-2">Dein Bezug</h3>
            <dl class="space-y-1.5 text-[11px]">
                <div class="flex items-baseline gap-3">
                    <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Rolle</dt>
                    <dd class="m-0">
                        @if($currentUserRole)
                            <span class="text-[var(--ui-secondary)]">{{ ucfirst($currentUserRole) }}</span>
                        @elseif($hasAnyTasks)
                            <span class="text-[var(--ui-muted)] italic">Nur Aufgaben</span>
                        @else
                            <span class="text-[var(--ui-muted)] italic">Beobachter</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-baseline gap-3">
                    <dt class="text-[var(--ui-muted)] w-20 flex-shrink-0">Offen</dt>
                    <dd class="text-[var(--ui-secondary)] m-0 tabular-nums">
                        {{ $userOpenTaskCount ?? 0 }}
                        @if(($userOpenTaskCount ?? 0) === 1) Aufgabe @else Aufgaben @endif
                    </dd>
                </div>
            </dl>
        </section>

        {{-- TEAM --}}
        @if($allProjectUsers->isNotEmpty())
            <section>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] m-0 mb-2">Team ({{ $allProjectUsers->count() }})</h3>
                <ul class="space-y-1">
                    @foreach($allProjectUsers as $pu)
                        @php
                            $name = $pu->user?->name ?? $pu->user?->email ?? 'Unbekannt';
                            $initial = mb_strtoupper(mb_substr($name, 0, 1));
                        @endphp
                        <li class="flex items-center gap-2 text-[11px]">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-secondary)] text-white text-[9px] font-semibold flex-shrink-0">{{ $initial }}</span>
                            <span class="truncate text-[var(--ui-secondary)] flex-1 min-w-0">{{ $name }}</span>
                            @if($pu->role)
                                <span class="text-[var(--ui-muted)] flex-shrink-0">{{ $pu->role }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

    </div>
</div>
