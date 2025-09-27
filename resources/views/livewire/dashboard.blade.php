<div class="h-full overflow-y-auto p-6">
    <x-ui-page-header :title="'Dashboard'" :subtitle="$currentDay.', '.$currentDate">
        <x-slot:actions>
            <x-ui-segmented-toggle 
                model="perspective"
                :current="$perspective"
                :options="[
                    ['value' => 'personal', 'label' => 'Persönlich', 'icon' => 'heroicon-o-user'],
                    ['value' => 'team', 'label' => 'Team', 'icon' => 'heroicon-o-users'],
                ]"
                active-variant="success"
                size="sm"
            />
        </x-slot:actions>
    </x-ui-page-header>

    @if($perspective === 'personal')
        <div class="mb-4">
            <x-ui-info-banner 
                icon="heroicon-o-user"
                title="Persönliche Übersicht"
                message="Deine persönlichen Aufgaben und zuständigen Projektaufgaben im aktuellen Sprint."
                variant="info"
            />
        </div>
    @else
        <div class="mb-4">
            <x-ui-info-banner 
                icon="heroicon-o-users"
                title="Team-Übersicht"
                message="Alle Aufgaben des Teams in aktiven Projekten und Sprints."
                variant="success"
            />
        </div>
    @endif

    <x-ui-stats-grid cols="4" gap="4">
        <x-ui-dashboard-tile
            title="Aktive Projekte"
            :count="$activeProjects"
            subtitle="von {{ $totalProjects }}"
            icon="folder"
            variant="primary"
            size="lg"
        />
        <x-ui-dashboard-tile
            title="Offene Aufgaben"
            :count="$openTasks"
            subtitle="von {{ $totalTasks }}"
            icon="clock"
            variant="warning"
            size="lg"
        />
        <x-ui-dashboard-tile
            title="Erledigte Aufgaben"
            :count="$completedTasks"
            subtitle="diesen Monat: {{ $monthlyCompletedTasks }}"
            icon="check-circle"
            variant="success"
            size="lg"
        />
        <x-ui-dashboard-tile
            title="Story Points"
            :count="$openStoryPoints"
            subtitle="erledigt: {{ $completedStoryPoints }}"
            icon="chart-bar"
            variant="info"
            size="lg"
        />
    </x-ui-stats-grid>

    <x-ui-detail-stats-grid cols="2" gap="6">
        <x-slot:left>
            <h3 class="text-lg font-semibold text-secondary mb-4">Aufgaben-Übersicht</h3>
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-dashboard-tile title="Frösche" :count="$frogTasks" icon="exclamation-triangle" variant="danger" size="sm" />
                <x-ui-dashboard-tile title="Überfällig" :count="$overdueTasks" icon="exclamation-circle" variant="danger" size="sm" />
                <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedTasks" icon="plus-circle" variant="neutral" size="sm" />
                <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedTasks" icon="check-circle" variant="success" size="sm" />
            </x-ui-form-grid>
        </x-slot:left>
        <x-slot:right>
            <h3 class="text-lg font-semibold text-secondary mb-4">Story Points Performance</h3>
            <x-ui-form-grid :cols="2" :gap="3">
                <x-ui-dashboard-tile title="Offen" :count="$openStoryPoints" icon="clock" variant="warning" size="sm" />
                <x-ui-dashboard-tile title="Erledigt" :count="$completedStoryPoints" icon="check-circle" variant="success" size="sm" />
                <x-ui-dashboard-tile title="Erstellt (Monat)" :count="$monthlyCreatedPoints" icon="plus-circle" variant="neutral" size="sm" />
                <x-ui-dashboard-tile title="Erledigt (Monat)" :count="$monthlyCompletedPoints" icon="check-circle" variant="success" size="sm" />
            </x-ui-form-grid>
        </x-slot:right>
    </x-ui-detail-stats-grid>

    <x-ui-panel class="mb-8" title="Team-Mitglieder Übersicht" subtitle="Aufgaben und Story Points pro Team-Mitglied">
        <x-ui-team-members-list :members="$teamMembers" />
    </x-ui-panel>

    <x-ui-panel title="Meine aktiven Projekte" subtitle="Top 5 Projekte nach offenen Aufgaben">
        <x-ui-project-list :projects="$activeProjectsList" projectRoute="planner.projects.show" />
    </x-ui-panel>
</div>