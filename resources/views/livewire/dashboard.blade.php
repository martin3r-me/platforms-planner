<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum und Perspektive-Toggle -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-600">{{ $currentDay }}, {{ $currentDate }}</p>
            </div>
            <div class="d-flex items-center gap-4">
                <!-- Perspektive-Toggle -->
                <div class="d-flex bg-gray-100 rounded-lg p-1">
                    <button 
                        wire:click="$set('perspective', 'personal')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'personal' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            <span>Persönlich</span>
                        </div>
                    </button>
                    <button 
                        wire:click="$set('perspective', 'team')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'team' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>Team</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Perspektive-spezifische Statistiken -->
    @if($perspective === 'personal')
        <!-- Persönliche Perspektive -->
        <div class="mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-user', 'w-5 h-5 text-blue-600')
                    <h3 class="text-lg font-semibold text-blue-900">Persönliche Übersicht</h3>
                </div>
                <p class="text-blue-700 text-sm">Deine persönlichen Aufgaben und zuständigen Projektaufgaben im aktuellen Sprint.</p>
            </div>
        </div>
    @else
        <!-- Team-Perspektive -->
        <div class="mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-users', 'w-5 h-5 text-green-600')
                    <h3 class="text-lg font-semibold text-green-900">Team-Übersicht</h3>
                </div>
                <p class="text-green-700 text-sm">Alle Aufgaben des Teams in aktiven Projekten und Sprints.</p>
            </div>
        </div>
    @endif

    <!-- Haupt-Statistiken (4x2 Grid) -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <!-- Projekte -->
        <x-ui-dashboard-tile
            title="Aktive Projekte"
            :count="$activeProjects"
            subtitle="von {{ $totalProjects }}"
            icon="folder"
            variant="primary"
            size="lg"
        />
        
        <!-- Aufgaben -->
        <x-ui-dashboard-tile
            title="Offene Aufgaben"
            :count="$openTasks"
            subtitle="von {{ $totalTasks }}"
            icon="clock"
            variant="warning"
            size="lg"
        />
        
        <!-- Erledigte Aufgaben -->
        <x-ui-dashboard-tile
            title="Erledigte Aufgaben"
            :count="$completedTasks"
            subtitle="diesen Monat: {{ $monthlyCompletedTasks }}"
            icon="check-circle"
            variant="success"
            size="lg"
        />
        
        <!-- Story Points -->
        <x-ui-dashboard-tile
            title="Story Points"
            :count="$openStoryPoints"
            subtitle="erledigt: {{ $completedStoryPoints }}"
            icon="chart-bar"
            variant="info"
            size="lg"
        />
    </div>

    <!-- Detaillierte Statistiken (2x3 Grid) -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Aufgaben-Details -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Aufgaben-Übersicht</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Frösche"
                    :count="$frogTasks"
                    icon="exclamation-triangle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Überfällig"
                    :count="$overdueTasks"
                    icon="exclamation-circle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erstellt (Monat)"
                    :count="$monthlyCreatedTasks"
                    icon="plus-circle"
                    variant="neutral"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erledigt (Monat)"
                    :count="$monthlyCompletedTasks"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
            </div>
        </div>

        <!-- Rechte Spalte: Story Points Performance -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Story Points Performance</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Offen"
                    :count="$openStoryPoints"
                    icon="clock"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erledigt"
                    :count="$completedStoryPoints"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erstellt (Monat)"
                    :count="$monthlyCreatedPoints"
                    icon="plus-circle"
                    variant="neutral"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Erledigt (Monat)"
                    :count="$monthlyCompletedPoints"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
            </div>
        </div>
    </div>

    <!-- Projekt-Übersicht -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Meine aktiven Projekte</h3>
            <p class="text-sm text-gray-600 mt-1">Top 5 Projekte nach offenen Aufgaben</p>
        </div>
        
        <div class="p-6">
            @if($activeProjectsList->count() > 0)
                <div class="space-y-4">
                    @foreach($activeProjectsList as $project)
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="d-flex items-center gap-4">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    <x-heroicon-o-folder class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $project['name'] }}</h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $project['open_tasks'] }} offene von {{ $project['total_tasks'] }} Aufgaben
                                        @if($project['story_points'] > 0)
                                            • {{ $project['story_points'] }} Story Points
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('planner.projects.show', $project['id']) }}" 
                               class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm"
                               wire:navigate>
                                <div class="d-flex items-center gap-2">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                    <span>Öffnen</span>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <x-heroicon-o-folder class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Keine aktiven Projekte</h4>
                    <p class="text-gray-600">Du hast noch keine Projekte oder bist in keinem Projekt zuständig.</p>
                </div>
            @endif
        </div>
    </div>
</div>