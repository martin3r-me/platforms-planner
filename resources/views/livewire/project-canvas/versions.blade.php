{{-- Versionsleiste des Projekt-Canvas: Kickoff · aktueller Stand · Stand festhalten · Verlauf --}}
@php
    $typeOptions = collect($types)->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();
    $sourceVariant = ['kickoff' => 'accent', 'manual' => 'info', 'agent' => 'info', 'restore' => 'warning', 'auto' => 'neutral'];
    $kickoffDone = (bool) $canvas->kickoff_completed_at;
@endphp
<div class="shrink-0">
    {{-- ── Kickoff offen ── --}}
    @if(! $kickoffDone)
        <div class="px-4 sm:px-6 py-3 border-b border-[color:var(--nx-line)] bg-[var(--nx-warning)]/10">
            <div class="flex flex-wrap items-start gap-3">
                @svg('heroicon-o-rocket-launch', 'w-5 h-5 flex-shrink-0 mt-0.5 text-[var(--nx-warning)]')
                <div class="flex-1 min-w-[16rem] text-xs text-[var(--nx-text)] space-y-0.5">
                    <div class="text-sm font-semibold">Kickoff</div>
                    <p class="m-0">Jedes Projekt beginnt hier: Ziel, Beteiligte, Nutzen, Rahmen und Risiken festhalten. Mit „Kickoff abschließen“ wird dieser Stand als <span class="font-medium">v1-Anker</span> gesichert und als Meilenstein ins Logbuch geschrieben — jede spätere Änderung ist dann als Abweichung vom Kickoff sichtbar.</p>
                </div>
                @if($canUpdate && ! $kickoffOpen)
                    <x-nx-button variant="primary" wire:click="$set('kickoffOpen', true)">Kickoff abschließen</x-nx-button>
                @endif
            </div>
            @if($kickoffOpen)
                <div class="mt-3 ml-8 space-y-2">
                    <x-nx-input-textarea name="kickoffNote" label="Notiz zum Kickoff (optional)" wire:model="kickoffNote" rows="2"
                                         placeholder="z.B. Wer war dabei, was ist bewusst offen geblieben?" />
                    @error('kickoff')<div class="text-xs text-[color:var(--nx-danger)]">{{ $message }}</div>@enderror
                    <div class="flex gap-2">
                        <x-nx-button size="sm" wire:click="$set('kickoffOpen', false)">Abbrechen</x-nx-button>
                        <x-nx-button variant="primary" size="sm" wire:click="completeKickoff">Kickoff festhalten</x-nx-button>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ── Versionsleiste ── --}}
    <div class="px-4 sm:px-6 py-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        <button type="button" wire:click="togglePanel"
                class="inline-flex items-center gap-1.5 text-[var(--nx-text)] hover:text-[var(--nx-accent)]">
            @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--nx-muted)]')
            @if($latest)
                <span class="font-medium">v{{ $latest->version }}</span>
                <span class="text-[var(--nx-muted)]">· gesichert {{ $latest->created_at?->diffForHumans() }}</span>
            @else
                <span class="text-[var(--nx-muted)]">Noch keine Version</span>
            @endif
            @svg($panelOpen ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down', 'w-3 h-3 text-[var(--nx-muted)]')
        </button>

        @if($kickoffDone)
            <span class="inline-flex items-center gap-1 text-[var(--nx-muted)]" title="Kickoff am {{ $canvas->kickoff_completed_at->format('d.m.Y') }}">
                @svg('heroicon-o-rocket-launch', 'w-3.5 h-3.5')
                Kickoff {{ $canvas->kickoff_completed_at->format('d.m.Y') }}
                @if($sinceKickoff && ($sinceKickoff['added'] + $sinceKickoff['removed'] + $sinceKickoff['modified']) > 0)
                    <button type="button" wire:click="select({{ $kickoffSnapshot->id }}); $set('panelOpen', true)" class="ml-1 text-[var(--nx-accent)] hover:underline">
                        seitdem +{{ $sinceKickoff['added'] }} / ~{{ $sinceKickoff['modified'] }} / −{{ $sinceKickoff['removed'] }}
                    </button>
                @elseif($sinceKickoff)
                    <span>· unverändert</span>
                @endif
            </span>
        @endif

        <span class="hidden md:inline text-[10px] text-[var(--nx-muted)]">Versionen entstehen automatisch je Bearbeitungssitzung ({{ $sessionGap }} Min. Pause).</span>

        @if($canUpdate)
            <div class="ml-auto">
                <x-nx-button size="sm" wire:click="$toggle('captureOpen')">
                    @svg('heroicon-o-bookmark', 'w-3.5 h-3.5') Stand festhalten
                </x-nx-button>
            </div>
        @endif
    </div>

    {{-- ── Stand festhalten (optional mit Logbuch-Eintrag) ── --}}
    @if($captureOpen)
        <div class="px-4 sm:px-6 py-3 border-b border-[color:var(--nx-line)] bg-[var(--nx-bg)]">
            <div class="max-w-2xl space-y-3">
                <x-nx-input-text name="capture.label" label="Bezeichnung des Stands" wire:model="capture.label" placeholder="z.B. Nach Abstimmung mit Kunde" />
                <label class="flex items-center gap-2 text-xs text-[var(--nx-text)] cursor-pointer">
                    <input type="checkbox" wire:model.live="capture.log" class="rounded border-[color:var(--nx-line)]">
                    Warum? — als Logbuch-Eintrag festhalten (verweist auf diesen Stand)
                </label>
                @if($capture['log'])
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <x-nx-input-select name="capture.type" label="Typ" wire:model="capture.type" :options="$typeOptions" />
                        <div class="sm:col-span-2"><x-nx-input-text name="capture.title" label="Was" wire:model="capture.title" placeholder="z.B. Zielgruppe auf Firmenkunden eingegrenzt" /></div>
                    </div>
                    <x-nx-input-textarea name="capture.rationale" label="Warum" wire:model="capture.rationale" rows="3" placeholder="Auslöser, Abwägung, verworfene Alternativen" />
                @endif
                @error('capture')<div class="text-xs text-[color:var(--nx-danger)]">{{ $message }}</div>@enderror
                <div class="flex gap-2">
                    <x-nx-button size="sm" wire:click="$set('captureOpen', false)">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" size="sm" wire:click="saveCapture">Festhalten</x-nx-button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Versionsverlauf ── --}}
    @if($panelOpen)
        <div class="border-b border-[color:var(--nx-line)] bg-[var(--nx-bg)] grid grid-cols-1 lg:grid-cols-[minmax(16rem,22rem)_1fr]">
            <ol class="max-h-[50vh] overflow-y-auto p-3 space-y-1 lg:border-r border-[color:var(--nx-line)]">
                @forelse($snapshots as $s)
                    <li>
                        <button type="button" wire:click="select({{ $s->id }})"
                                class="w-full text-left px-2.5 py-1.5 rounded-md transition-colors {{ $selected?->id === $s->id ? 'bg-[color:var(--nx-surface)] shadow-[var(--nx-shadow-card)]' : 'hover:bg-[color:var(--nx-surface)]' }}">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-semibold tabular-nums text-[var(--nx-text)]">v{{ $s->version }}</span>
                                <x-nx-badge :variant="$sourceVariant[$s->source] ?? 'neutral'">{{ $sourceLabels[$s->source] ?? $s->source }}</x-nx-badge>
                                <span class="ml-auto text-[10px] text-[var(--nx-muted)] tabular-nums">{{ $s->created_at?->format('d.m.y H:i') }}</span>
                            </div>
                            @if($s->label || $s->createdByUser)
                                <div class="mt-0.5 text-[11px] text-[var(--nx-muted)] truncate">{{ $s->label }}@if($s->label && $s->createdByUser) · @endif{{ $s->createdByUser?->name }}</div>
                            @endif
                        </button>
                    </li>
                @empty
                    <li class="text-xs text-[var(--nx-muted)] p-2">Noch keine Versionen. Die erste entsteht mit dem Kickoff oder sobald das Canvas nach einer Pause wieder bearbeitet wird.</li>
                @endforelse
            </ol>

            <div class="p-4 max-h-[50vh] overflow-y-auto">
                @if($selected && $diff)
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <div class="text-sm font-semibold text-[var(--nx-text)]">v{{ $selected->version }} → heute</div>
                        <span class="text-[11px] text-[var(--nx-muted)]">
                            +{{ $diff['summary']['added'] }} neu · ~{{ $diff['summary']['modified'] }} geändert · −{{ $diff['summary']['removed'] }} entfernt
                        </span>
                        @if($canUpdate && $diff['has_changes'])
                            <div class="ml-auto flex items-center gap-2">
                                @if($confirmRestoreId === $selected->id)
                                    <span class="text-[11px] text-[var(--nx-text)]">Aktuellen Stand durch v{{ $selected->version }} ersetzen? (Der jetzige bleibt als Version erhalten.)</span>
                                    <x-nx-button size="sm" wire:click="$set('confirmRestoreId', null)">Nein</x-nx-button>
                                    <x-nx-button variant="danger" size="sm" wire:click="restore">Wiederherstellen</x-nx-button>
                                @else
                                    <x-nx-button size="sm" wire:click="askRestore({{ $selected->id }})">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-3.5 h-3.5') Diesen Stand wiederherstellen
                                    </x-nx-button>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if(! $diff['has_changes'])
                        <p class="text-xs text-[var(--nx-muted)]">Der aktuelle Stand entspricht inhaltlich v{{ $selected->version }}.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($diff['changes'] as $block)
                                <section>
                                    <div class="text-[10px] uppercase tracking-wider font-semibold text-[var(--nx-muted)] mb-1">{{ $block['label'] }}</div>
                                    <ul class="space-y-1 text-xs">
                                        @foreach($block['added'] as $e)
                                            <li class="flex gap-2 px-2 py-1 rounded bg-[var(--nx-success)]/10">
                                                <span class="font-semibold text-[var(--nx-success)]">+</span>
                                                <span class="text-[var(--nx-text)]"><span class="font-medium">{{ $e['title'] ?? '' }}</span>@if(! empty($e['content'])) — {{ \Illuminate\Support\Str::limit($e['content'], 160) }}@endif</span>
                                            </li>
                                        @endforeach
                                        @foreach($block['modified'] as $m)
                                            <li class="px-2 py-1 rounded bg-[var(--nx-info)]/10 space-y-0.5">
                                                <div class="flex gap-2"><span class="font-semibold text-[var(--nx-info)]">~</span><span class="text-[var(--nx-text)] font-medium">{{ $m['after']['title'] ?? '' }}</span></div>
                                                <div class="pl-4 text-[var(--nx-muted)] line-through">{{ \Illuminate\Support\Str::limit(trim(($m['before']['title'] ?? '') . ' — ' . ($m['before']['content'] ?? ''), ' —'), 160) }}</div>
                                                <div class="pl-4 text-[var(--nx-text)]">{{ \Illuminate\Support\Str::limit(trim(($m['after']['title'] ?? '') . ' — ' . ($m['after']['content'] ?? ''), ' —'), 160) }}</div>
                                            </li>
                                        @endforeach
                                        @foreach($block['removed'] as $e)
                                            <li class="flex gap-2 px-2 py-1 rounded bg-[var(--nx-danger)]/10">
                                                <span class="font-semibold text-[var(--nx-danger)]">−</span>
                                                <span class="text-[var(--nx-muted)] line-through">{{ $e['title'] ?? '' }}@if(! empty($e['content'])) — {{ \Illuminate\Support\Str::limit($e['content'], 160) }}@endif</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="text-xs text-[var(--nx-muted)]">Version links wählen — hier erscheint, was sich seitdem geändert hat.</p>
                @endif
            </div>
        </div>
    @endif
</div>
