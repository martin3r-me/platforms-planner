@php
    use Platform\Planner\Enums\LogbookEntryType;

    $typeVariant = [
        'decision' => 'info',
        'milestone' => 'success',
        'risk' => 'warning',
    ];
    $refIcon = [
        'task' => 'heroicon-o-check-circle',
        'commit' => 'heroicon-o-code-bracket',
        'canvas' => 'heroicon-o-squares-2x2',
        'canvas_snapshot' => 'heroicon-o-camera',
        'url' => 'heroicon-o-link',
    ];
    $refTypeOptions = [
        ['value' => 'commit', 'label' => 'Commit (sha / repo@sha)'],
        ['value' => 'task', 'label' => 'Aufgabe (ID)'],
        ['value' => 'canvas', 'label' => 'Canvas (ID)'],
        ['value' => 'canvas_snapshot', 'label' => 'Canvas-Stand (ID)'],
        ['value' => 'url', 'label' => 'URL'],
    ];
    $typeOptions = collect($types)->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$project->title" icon="heroicon-o-book-open" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('planner.dashboard'), 'icon' => 'home'],
            ['label' => $project->title, 'href' => route('planner.projects.show', $project)],
        ]">
            <x-slot name="left">
                @include('planner::livewire.partials.project-tabs', ['project' => $project, 'active' => 'logbook'])
            </x-slot>
            @if($canUpdate)
                <x-nx-button variant="secondary" size="sm" wire:click="generateDraft" wire:loading.attr="disabled" wire:target="generateDraft" title="Faktenprosa seit dem letzten Eintrag als Ausgangspunkt">
                    <span wire:loading.remove wire:target="generateDraft" class="inline-flex items-center gap-1.5">@svg('heroicon-o-sparkles', 'w-3.5 h-3.5') Entwurf</span>
                    <span wire:loading wire:target="generateDraft">Sammle Fakten…</span>
                </x-nx-button>
                <x-nx-button variant="primary" size="sm" wire:click="openForm">
                    @svg('heroicon-o-plus', 'w-3.5 h-3.5') Eintrag
                </x-nx-button>
            @endif

            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = !open"
                    class="inline-flex items-center justify-center w-8 h-7 rounded-md text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors"
                    title="Mehr"
                >
                    @svg('heroicon-o-ellipsis-horizontal', 'w-4 h-4')
                </button>
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity.duration.100ms
                    @click.outside="open = false"
                    @keydown.escape.window="open = false"
                    class="absolute top-full right-0 mt-1 w-52 bg-[color:var(--nx-surface)] border border-[var(--nx-line-strong)] rounded-lg shadow-[var(--nx-shadow-pop)] z-30 py-1"
                >
                    <a href="{{ route('planner.projects.show', $project) }}" wire:navigate @click="open = false"
                       class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors">
                        @svg('heroicon-o-clipboard-document-list', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Zum Board</span>
                    </a>
                    <a href="{{ route('planner.projects.canvas.index', $project) }}" wire:navigate @click="open = false"
                       class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Project Canvas</span>
                    </a>
                    <a href="{{ route('planner.projects.health', $project) }}" wire:navigate @click="open = false"
                       class="w-full inline-flex items-center gap-2 px-3 py-1.5 text-xs text-left text-[var(--nx-text)] hover:bg-[var(--nx-bg)] transition-colors">
                        @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--nx-muted)]')
                        <span>Health-Sicht</span>
                    </a>
                </div>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- ════════ LEFT SIDEBAR: Filter + Abgrenzung ════════ --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Logbuch" icon="heroicon-o-book-open" width="w-72" :minWidth="240" :maxWidth="380" :defaultOpen="true" storeKey="sidebarOpen" side="left">
            <div class="p-4 space-y-5">
                <section class="space-y-1">
                    <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)] font-semibold mb-1.5">Typ</div>
                    <button type="button" wire:click="$set('typeFilter', '')"
                            class="w-full flex items-center justify-between px-2 py-1 rounded-md text-xs {{ $typeFilter === '' ? 'bg-[var(--nx-bg)] text-[var(--nx-text)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)]' }}">
                        <span>Alle</span>
                        <span class="tabular-nums">{{ $counts->sum() }}</span>
                    </button>
                    @foreach($types as $type)
                        <button type="button" wire:click="$set('typeFilter', '{{ $type->value }}')"
                                class="w-full flex items-center justify-between px-2 py-1 rounded-md text-xs {{ $typeFilter === $type->value ? 'bg-[var(--nx-bg)] text-[var(--nx-text)] font-medium' : 'text-[var(--nx-muted)] hover:bg-[var(--nx-bg)]' }}">
                            <span class="inline-flex items-center gap-1.5">@svg($type->icon(), 'w-3.5 h-3.5') {{ $type->label() }}</span>
                            <span class="tabular-nums">{{ $counts[$type->value] ?? 0 }}</span>
                        </button>
                    @endforeach
                    <label class="flex items-center gap-2 px-2 pt-2 text-xs text-[var(--nx-muted)] cursor-pointer">
                        <input type="checkbox" wire:model.live="showSuperseded" class="rounded border-[color:var(--nx-line)]">
                        Ersetzte Einträge zeigen
                    </label>
                </section>

                <section class="space-y-1.5 text-xs text-[var(--nx-muted)] leading-relaxed">
                    <div class="text-[10px] uppercase tracking-wider font-semibold">Wofür</div>
                    <p><span class="text-[var(--nx-text)] font-medium">Canvas</span> = Zustand, wird überschrieben.</p>
                    <p><span class="text-[var(--nx-text)] font-medium">Logbuch</span> = Verlauf: Wendepunkte und ihr Warum, nur anhängen.</p>
                    <p><span class="text-[var(--nx-text)] font-medium">Aufgaben/Zeiten</span> = Arbeit. Hier nur verweisen, nicht wiederholen.</p>
                    @if($lastEntryAt)
                        <p class="pt-1">Letzter Eintrag: {{ \Illuminate\Support\Carbon::parse($lastEntryAt)->diffForHumans() }}</p>
                    @endif
                </section>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container width="contained" spacing="space-y-6" background="bg-[color:var(--nx-bg)]">

        {{-- ════════ Formular (anhängen) ════════ --}}
        @if($formOpen)
            <div class="rounded-lg border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-[var(--nx-text)]">
                        {{ $form['supersedes_entry_id'] ? 'Eintrag korrigieren (ersetzt den alten)' : 'Neuer Eintrag' }}
                    </div>
                    <span class="text-[11px] text-[var(--nx-muted)]">Einträge sind nach dem Speichern unveränderlich.</span>
                </div>

                @if($draft)
                    <div class="rounded-md border border-[color:var(--nx-line)] bg-[var(--nx-bg)] p-3 space-y-1.5">
                        <div class="flex items-center justify-between text-[11px] text-[var(--nx-muted)]">
                            <span class="inline-flex items-center gap-1">@svg('heroicon-o-sparkles', 'w-3.5 h-3.5') Faktenlage seit {{ \Illuminate\Support\Carbon::parse($draft['since'])->format('d.m.Y') }} — nur Material, kein Eintrag</span>
                            @unless($draft['llm'])<span>ohne LLM (Faktenbasis)</span>@endunless
                        </div>
                        <div class="text-xs text-[var(--nx-text)] whitespace-pre-line max-h-56 overflow-y-auto">{{ $draft['prose'] ?? $draft['fact_sheet'] ?? 'Keine Fakten gefunden.' }}</div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <x-nx-input-select name="form.type" label="Typ" wire:model="form.type" :options="$typeOptions" />
                    <div class="sm:col-span-2">
                        <x-nx-input-text name="form.occurred_on" label="Datum" type="date" wire:model="form.occurred_on" />
                    </div>
                </div>

                <x-nx-input-text name="form.title" label="Was" wire:model="form.title" required
                                 placeholder="z.B. Wir lösen BankettProfi durch NECTA ab" />

                <x-nx-input-textarea name="form.rationale" label="Warum" wire:model="form.rationale" rows="4"
                                     placeholder="Begründung, verworfene Alternativen, Annahmen, Auslöser" />

                {{-- Verweise --}}
                <div class="space-y-2">
                    <div class="text-xs font-medium text-[var(--nx-text)]">Verweise</div>
                    @if(count($form['refs']))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($form['refs'] as $i => $ref)
                                <span class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-md border border-[color:var(--nx-line)] text-[11px] text-[var(--nx-text)]">
                                    @svg($refIcon[$ref['type']] ?? 'heroicon-o-link', 'w-3 h-3 text-[var(--nx-muted)]')
                                    {{ $ref['label'] ?? $ref['ref'] }}
                                    <button type="button" wire:click="removeRef({{ $i }})" class="p-0.5 rounded hover:bg-[var(--nx-bg)] text-[var(--nx-muted)]" title="Entfernen">
                                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-52"><x-nx-input-select name="form.new_ref_type" size="sm" wire:model="form.new_ref_type" :options="$refTypeOptions" /></div>
                        <div class="flex-1 min-w-[10rem]"><x-nx-input-text name="form.new_ref" size="sm" wire:model="form.new_ref" wire:keydown.enter.prevent="addRef" placeholder="Referenz" /></div>
                        <x-nx-button size="sm" wire:click="addRef">Hinzufügen</x-nx-button>
                    </div>
                </div>

                @error('form')
                    <div class="text-xs text-[color:var(--nx-danger)]">{{ $message }}</div>
                @enderror

                <div class="flex justify-end gap-2">
                    <x-nx-button size="sm" wire:click="closeForm">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" size="sm" wire:click="save">Anhängen</x-nx-button>
                </div>
            </div>
        @endif

        {{-- ════════ Zeitleiste ════════ --}}
        @if($entries->isEmpty())
            <x-nx-empty icon="heroicon-o-book-open">
                Noch keine Einträge. Halte Entscheidungen, Meilensteine und Risiken mit ihrem Warum fest.
                @if($canUpdate)
                    <x-slot name="action"><x-nx-button size="sm" wire:click="generateDraft">Entwurf aus der Faktenlage</x-nx-button></x-slot>
                @endif
            </x-nx-empty>
        @else
            @foreach($grouped as $month => $monthEntries)
                <section class="space-y-2">
                    <div class="text-[10px] uppercase tracking-wider text-[var(--nx-muted)] font-semibold">
                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->locale('de')->isoFormat('MMMM YYYY') }}
                    </div>
                    <ol class="relative border-l border-[color:var(--nx-line)] ml-2 space-y-3">
                        @foreach($monthEntries as $entry)
                            @php $superseded = $entry->supersededBy !== null; @endphp
                            <li id="eintrag-{{ $entry->id }}" wire:key="logbook-entry-{{ $entry->id }}" class="ml-4 scroll-mt-6 [&:target>div]:ring-2 [&:target>div]:ring-[color:var(--nx-accent)] {{ $superseded ? 'opacity-50' : '' }}">
                                <span class="absolute -left-[7px] mt-3 w-3 h-3 rounded-full border-2 border-[color:var(--nx-surface)] bg-[color:var(--nx-line-strong)]"></span>
                                <div class="rounded-lg border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-3 space-y-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1 min-w-0">
                                            <div class="flex items-center gap-2 text-[11px] text-[var(--nx-muted)]">
                                                <x-nx-badge :variant="$typeVariant[$entry->type->value] ?? 'neutral'">
                                                    @svg($entry->type->icon(), 'w-3 h-3') {{ $entry->type->label() }}
                                                </x-nx-badge>
                                                <span class="tabular-nums">{{ $entry->occurred_on->format('d.m.Y') }}</span>
                                                @if($entry->createdBy)<span>· {{ $entry->createdBy->name }}</span>@endif
                                                @if($entry->source === 'agent')<span>· via Agent</span>@endif
                                                @if($entry->supersedes_entry_id)<span>· Korrektur</span>@endif
                                                @if($superseded)<span>· ersetzt</span>@endif
                                            </div>
                                            <div class="text-sm font-medium text-[var(--nx-text)] {{ $superseded ? 'line-through' : '' }}">{{ $entry->title }}</div>
                                        </div>
                                        @if($canUpdate && ! $superseded)
                                            <x-nx-button variant="ghost" size="sm" wire:click="openForm({{ $entry->id }})" title="Korrigieren: neuer Eintrag, der diesen ersetzt">
                                                @svg('heroicon-o-arrow-uturn-right', 'w-3.5 h-3.5')
                                            </x-nx-button>
                                        @endif
                                    </div>
                                    @if($entry->rationale)
                                        <div class="text-xs text-[var(--nx-text)] whitespace-pre-line leading-relaxed">{{ $entry->rationale }}</div>
                                    @endif
                                    @if(! empty($entry->refs))
                                        <div class="flex flex-wrap gap-1.5 pt-0.5">
                                            @foreach($entry->refs as $ref)
                                                @php $url = $this->refUrl($ref); @endphp
                                                @if($url)
                                                    <a href="{{ $url }}" @if($ref['type'] === 'url') target="_blank" rel="noopener" @else wire:navigate @endif
                                                       class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-[color:var(--nx-line)] text-[11px] text-[var(--nx-muted)] hover:text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                                                        @svg($refIcon[$ref['type']] ?? 'heroicon-o-link', 'w-3 h-3') {{ $ref['label'] ?? $ref['ref'] }}
                                                    </a>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border border-[color:var(--nx-line)] text-[11px] text-[var(--nx-muted)] {{ $ref['type'] === 'commit' ? 'font-mono' : '' }}">
                                                        @svg($refIcon[$ref['type']] ?? 'heroicon-o-link', 'w-3 h-3') {{ $ref['label'] ?? $ref['ref'] }}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        @endif
    </x-ui-page-container>
</x-ui-page>
