<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Export" icon="heroicon-o-arrow-down-tray" />
    </x-slot>

    <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">

        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-[var(--ui-primary-text)]">Export-Bereich</h1>
            <p class="text-sm text-[var(--ui-secondary)] mt-1">
                Exportiere Aufgaben und Projekte als JSON oder PDF. Weitere Formate folgen.
            </p>
        </div>

        {{-- Export-Formular --}}
        <div class="bg-[var(--ui-surface)] border border-[var(--ui-border)] rounded-xl p-6 space-y-6">

            {{-- Export-Typ --}}
            <div>
                <label class="block text-sm font-semibold text-[var(--ui-primary-text)] mb-3">Was exportieren?</label>
                <div class="flex gap-3">
                    <button
                        type="button"
                        wire:click="$set('exportType', 'project')"
                        class="flex-1 px-4 py-3 rounded-lg text-sm font-medium transition-colors border
                            {{ $exportType === 'project'
                                ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border-[var(--ui-primary)]/30'
                                : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border-transparent hover:bg-[var(--ui-muted)]' }}"
                    >
                        <span class="block text-lg mb-1">Projekt</span>
                        <span class="block text-xs opacity-70">Ganzes Projekt inkl. aller Slots und Aufgaben</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('exportType', 'task')"
                        class="flex-1 px-4 py-3 rounded-lg text-sm font-medium transition-colors border
                            {{ $exportType === 'task'
                                ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border-[var(--ui-primary)]/30'
                                : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border-transparent hover:bg-[var(--ui-muted)]' }}"
                    >
                        <span class="block text-lg mb-1">Einzelne Aufgabe</span>
                        <span class="block text-xs opacity-70">Eine bestimmte Aufgabe mit allen Details</span>
                    </button>
                </div>
            </div>

            {{-- Auswahl Projekt/Aufgabe --}}
            @if($exportType === 'project')
                <div>
                    <label for="project-select" class="block text-sm font-semibold text-[var(--ui-primary-text)] mb-2">Projekt auswählen</label>
                    <select
                        id="project-select"
                        wire:model.live="selectedProjectId"
                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-primary-text)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--ui-primary)]/50 focus:border-[var(--ui-primary)]"
                    >
                        <option value="">-- Projekt wählen --</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">
                                {{ $project->name }}
                                @if($project->done) (Abgeschlossen) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label for="task-select" class="block text-sm font-semibold text-[var(--ui-primary-text)] mb-2">Aufgabe auswählen</label>
                    <select
                        id="task-select"
                        wire:model.live="selectedTaskId"
                        class="w-full rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-primary-text)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--ui-primary)]/50 focus:border-[var(--ui-primary)]"
                    >
                        <option value="">-- Aufgabe wählen --</option>
                        @foreach($tasks as $task)
                            <option value="{{ $task->id }}">
                                {{ $task->title }}
                                @if($task->project) ({{ $task->project->name }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Format --}}
            <div>
                <label class="block text-sm font-semibold text-[var(--ui-primary-text)] mb-3">Format</label>
                <div class="flex gap-3">
                    @foreach($formats as $format)
                        <button
                            type="button"
                            wire:click="$set('exportFormat', '{{ $format['key'] }}')"
                            class="px-5 py-2 rounded-lg text-sm font-medium transition-colors border
                                {{ $exportFormat === $format['key']
                                    ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border-[var(--ui-primary)]/30'
                                    : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border-transparent hover:bg-[var(--ui-muted)]' }}"
                        >
                            {{ $format['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Export-Button --}}
            <div class="pt-2">
                <button
                    type="button"
                    wire:click="startExport"
                    @if(($exportType === 'project' && !$selectedProjectId) || ($exportType === 'task' && !$selectedTaskId))
                        disabled
                    @endif
                    class="px-6 py-3 rounded-lg text-sm font-semibold transition-colors
                        {{ (($exportType === 'project' && $selectedProjectId) || ($exportType === 'task' && $selectedTaskId))
                            ? 'bg-[rgb(var(--ui-primary-rgb))] text-white hover:opacity-90 cursor-pointer'
                            : 'bg-[var(--ui-muted)] text-[var(--ui-secondary)] cursor-not-allowed opacity-50' }}"
                >
                    Exportieren & Herunterladen
                </button>
            </div>
        </div>

        {{-- Schnellexport: Projekte --}}
        <div class="space-y-4">
            <h2 class="text-lg font-bold text-[var(--ui-primary-text)]">Projekte – Schnellexport</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($projects as $project)
                    <div class="bg-[var(--ui-surface)] border border-[var(--ui-border)] rounded-xl p-4">
                        <div class="font-semibold text-sm text-[var(--ui-primary-text)] mb-1 truncate" title="{{ $project->name }}">
                            {{ $project->name }}
                        </div>
                        <div class="text-xs text-[var(--ui-secondary)] mb-3">
                            {{ $project->tasks()->count() }} Aufgaben
                            @if($project->done)
                                &middot; Abgeschlossen
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                wire:click="downloadProject({{ $project->id }}, 'json')"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)] transition-colors border border-[var(--ui-border)]"
                            >
                                JSON
                            </button>
                            <button
                                type="button"
                                wire:click="downloadProject({{ $project->id }}, 'pdf')"
                                class="flex-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted)] transition-colors border border-[var(--ui-border)]"
                            >
                                PDF
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-sm text-[var(--ui-secondary)]">
                        Keine Projekte vorhanden.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Info-Bereich --}}
        <div class="bg-[var(--ui-muted-5)] border border-[var(--ui-border)] rounded-xl p-5">
            <h3 class="text-sm font-semibold text-[var(--ui-primary-text)] mb-2">Hinweise zum Export</h3>
            <ul class="text-xs text-[var(--ui-secondary)] space-y-1 list-disc list-inside">
                <li><strong>JSON:</strong> Maschinenlesbares Format mit allen Daten, Extrafeldern und DoD-Items. Ideal für Backup und Integration.</li>
                <li><strong>PDF:</strong> Druckfertiges, formatiertes Dokument mit übersichtlichem Layout. Ideal für Dokumentation und Berichte.</li>
                <li>Einzelne Aufgaben enthalten alle Metadaten, Beschreibung, DoD und Extrafelder.</li>
                <li>Projektexporte enthalten alle Slots, Aufgaben, Team-Mitglieder und Statistiken.</li>
                <li>Weitere Formate (CSV, Excel) werden in Zukunft verfügbar sein.</li>
            </ul>
        </div>
    </div>
</x-ui-page>
