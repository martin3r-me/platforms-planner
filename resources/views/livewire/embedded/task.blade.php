<div class="h-full">
    <x-ui-page>
        <x-slot name="navbar">
            <x-ui-page-navbar title="Aufgabe" icon="heroicon-o-clipboard-document-check">
                {{-- Simple Breadcrumbs für Embedded --}}
                <div class="flex items-center space-x-2 text-sm">
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-home', 'w-4 h-4')
                        Teams
                    </span>
                    @if($task->project)
                        <span class="text-[var(--ui-muted)]">›</span>
                        <a href="{{ route('planner.embedded.project', $task->project) }}" class="text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] flex items-center gap-1">
                            @svg('heroicon-o-folder', 'w-4 h-4')
                            {{ $task->project->name }}
                        </a>
                    @endif
                    <span class="text-[var(--ui-muted)]">›</span>
                    <span class="text-[var(--ui-muted)] flex items-center gap-1">
                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                        {{ $task->title }}
                    </span>
                </div>
                
                @if($printingAvailable)
                    <x-ui-button variant="secondary" size="sm" wire:click="printTask()">
                        @svg('heroicon-o-printer', 'w-4 h-4')
                        <span class="hidden sm:inline ml-1">Drucken</span>
                    </x-ui-button>
                @endif
                <x-ui-confirm-button 
                    action="deleteTaskAndReturnToProject" 
                    text="Löschen" 
                    confirmText="Wirklich löschen?" 
                    variant="danger"
                    :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                />
            </x-ui-page-navbar>
        </x-slot>

        <x-ui-page-container spacing="space-y-4" class="p-4">
            <div class="bg-white rounded-lg border p-4">
                <x-ui-form-grid :cols="1" :gap="4" class="md:grid-cols-2">
                    <div>
                        <x-ui-input-text
                            name="task.title"
                            label="Titel"
                            wire:model.live.debounce.1000ms="task.title"
                            placeholder="Aufgabentitel eingeben..."
                            required
                            :errorKey="'task.title'"
                        />
                    </div>
                    <div>
                        <x-ui-input-select
                            name="task.priority"
                            label="Priorität"
                            :options="\Platform\Planner\Enums\TaskPriority::cases()"
                            optionValue="value"
                            optionLabel="label"
                            :nullable="false"
                            wire:model.live="task.priority"
                        />
                    </div>
                    <div>
                        <x-ui-input-datetime
                            name="dueDateInput"
                            label="Fälligkeitsdatum"
                            :value="$dueDateInput"
                            wire:model="dueDateInput"
                            placeholder="Fälligkeitsdatum auswählen..."
                            :nullable="true"
                            :errorKey="'dueDateInput'"
                        />
                    </div>
                    <div>
                        <x-ui-input-select
                            name="task.user_in_charge_id"
                            label="Verantwortlicher"
                            :options="($teamUsers ?? collect([]))"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Verantwortlichen auswählen –"
                            wire:model.live="task.user_in_charge_id"
                        />
                    </div>
                </x-ui-form-grid>

                <div class="mt-6 pt-6 border-t">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui-input-checkbox
                            model="task.is_done"
                            checked-label="Erledigt"
                            unchecked-label="Als erledigt markieren"
                            size="md"
                            block="true"
                        />
                        <x-ui-input-checkbox
                            model="task.is_frog"
                            checked-label="Frosch (wichtig & unangenehm)"
                            unchecked-label="Als Frosch markieren"
                            size="md"
                            block="true"
                        />
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t">
                    <x-ui-input-textarea
                        name="task.description"
                        label="Beschreibung"
                        wire:model.live.debounce.1000ms="task.description"
                        placeholder="Aufgabenbeschreibung (optional)"
                        rows="3"
                        :errorKey="'task.description'"
                    />
                </div>
            </div>
        </x-ui-page-container>

        {{-- Linke Sidebar --}}
        <x-slot name="sidebar">
            <x-ui-page-sidebar title="Navigation & Details" width="w-80" :defaultOpen="true">
                <div class="p-6 space-y-6">
                    {{-- Navigation --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Navigation</h3>
                        <div class="space-y-2">
                            @if($task->project)
                                <x-ui-button
                                    variant="secondary-outline"
                                    size="sm"
                                    :href="route('planner.embedded.project', $task->project)"
                                    wire:navigate
                                    class="w-full"
                                >
                                    <span class="flex items-center gap-2">
                                        @svg('heroicon-o-folder', 'w-4 h-4')
                                        Zum Projekt
                                    </span>
                                </x-ui-button>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Stats --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Status</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Erledigt</span>
                                <x-ui-input-checkbox
                                    model="task.is_done"
                                    checked-label=""
                                    unchecked-label=""
                                    size="sm"
                                    block="false"
                                />
                            </div>
                            <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Frosch</span>
                                <x-ui-input-checkbox
                                    model="task.is_frog"
                                    checked-label=""
                                    unchecked-label=""
                                    size="sm"
                                    block="false"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Metrics --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">Metriken</h3>
                        <div class="space-y-3">
                            @if($task->userInCharge)
                                <div class="py-3 px-4 bg-[var(--ui-info-5)] rounded-lg border-l-4 border-[var(--ui-info)]">
                                    <div class="text-xs text-[var(--ui-info)] font-medium uppercase tracking-wide">Verantwortlicher</div>
                                    <div class="text-lg font-bold text-[var(--ui-info)]">{{ $task->userInCharge->fullname ?? $task->userInCharge->name }}</div>
                                </div>
                            @endif
                            <div class="py-3 px-4 bg-[var(--ui-primary-5)] rounded-lg border-l-4 border-[var(--ui-primary)]">
                                <div class="text-xs text-[var(--ui-primary)] font-medium uppercase tracking-wide">Priorität</div>
                                <div class="text-lg font-bold text-[var(--ui-primary)]">{{ $task->priority?->label ?? 'Nicht gesetzt' }}</div>
                            </div>
                            @if($task->due_date)
                                <div class="py-3 px-4 bg-[var(--ui-warning-5)] rounded-lg border-l-4 border-[var(--ui-warning)]">
                                    <div class="text-xs text-[var(--ui-warning)] font-medium uppercase tracking-wide">Fälligkeitsdatum</div>
                                    <div class="text-lg font-bold text-[var(--ui-warning)]">{{ $task->due_date->format('d.m.Y') }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Debug Info für Teams --}}
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-4">🔍 Debug</h3>
                        <div class="space-y-2">
                            @php
                                $teamsUser = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsUser(request());
                                $teamsContext = \Platform\Core\Helpers\TeamsAuthHelper::getTeamsContext(request());
                                $authUser = auth()->user();
                            @endphp
                            
                            <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="text-xs font-medium text-[var(--ui-secondary)]">Teams User</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    @if($teamsUser)
                                        ✅ {{ $teamsUser['email'] ?? 'Keine Email' }}
                                    @else
                                        ❌ Nicht gefunden
                                    @endif
                                </div>
                            </div>
                            
                            <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="text-xs font-medium text-[var(--ui-secondary)]">Laravel Auth</div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    @if($authUser)
                                        ✅ {{ $authUser->email }}
                                    @else
                                        ❌ Nicht angemeldet
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>

        {{-- Rechte Sidebar --}}
        <x-slot name="activity">
            <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
                <div class="p-4 space-y-4">
                    <div class="text-sm text-[var(--ui-muted)]">Letzte Aktivitäten</div>
                    <div class="space-y-3 text-sm">
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Aufgabe geöffnet</div>
                            <div class="text-[var(--ui-muted)]">Gerade eben</div>
                        </div>
                        <div class="p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">Teams Tab erstellt</div>
                            <div class="text-[var(--ui-muted)]">Vor 5 Minuten</div>
                        </div>
                    </div>
                </div>
            </x-ui-page-sidebar>
        </x-slot>
    </x-ui-page>

    {{-- Print Modal --}}
    @if($printingAvailable && $printModalShow)
        <x-ui-modal wire:model="printModalShow" title="Aufgabe drucken">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Druckziel auswählen</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="printer" class="mr-2">
                            <span>Einzelner Drucker</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" wire:model="printTarget" value="group" class="mr-2">
                            <span>Druckergruppe</span>
                        </label>
                    </div>
                </div>

                @if($printTarget === 'printer')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Drucker auswählen</label>
                        <select wire:model="selectedPrinterId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Drucker auswählen --</option>
                            @foreach($printers as $printer)
                                <option value="{{ $printer->id }}">{{ $printer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if($printTarget === 'group')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Druckergruppe auswählen</label>
                        <select wire:model="selectedPrinterGroupId" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">-- Gruppe auswählen --</option>
                            @foreach($printerGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <x-ui-button variant="secondary" wire:click="closePrintModal">Abbrechen</x-ui-button>
                <x-ui-button 
                    variant="primary" 
                    wire:click="printTaskConfirm"
                    :disabled="($printTarget === 'printer' && !$selectedPrinterId) || ($printTarget === 'group' && !$selectedPrinterGroupId)"
                >
                    Drucken
                </x-ui-button>
            </x-slot>
        </x-ui-modal>
    @endif
</div>


