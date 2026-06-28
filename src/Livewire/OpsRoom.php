<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Planner\Models\PlannerProjectSnapshot;
use Platform\Planner\Models\PlannerTask;

/**
 * Cybersyn-style wall-display für den Planner.
 *
 * Hybrid:
 *  - "Stand" = juengster Tages-Snapshot (Ampel-Verteilung, Top-5 rot, Karteileichen)
 *  - "Heute" = Live-Counts direkt aus den Tasks (refresht alle 60s via wire:poll)
 */
class OpsRoom extends Component
{
    public function loadLatestSnapshots(): Collection
    {
        $teamId = Auth::user()->currentTeam->id;

        $latestIds = DB::table('planner_project_snapshots as a')
            ->where('a.team_id', $teamId)
            ->whereRaw('a.taken_on = (
                SELECT MAX(b.taken_on) FROM planner_project_snapshots b
                WHERE b.project_id = a.project_id
            )')
            ->pluck('a.id');

        return PlannerProjectSnapshot::with(['project:id,name,kind,status,done'])
            ->whereIn('id', $latestIds)
            ->get()
            ->filter(fn ($s) => ! ($s->project?->done ?? false))
            ->values();
    }

    #[Layout('planner::layouts.opsroom')]
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // ── STAND (aus Snapshots) ──
        $snapshots = $this->loadLatestSnapshots();
        $total = $snapshots->count();

        $byColor = ['red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0];
        foreach ($snapshots as $s) {
            $key = $s->health_color ?: 'gray';
            $byColor[$key] = ($byColor[$key] ?? 0) + 1;
        }

        $colorRank = ['red' => 0, 'yellow' => 1, 'gray' => 2, 'green' => 3];
        $brennt = $snapshots
            ->filter(fn ($s) => $s->health_color === 'red')
            ->sortBy(fn ($s) => (int) ($s->health_score ?? 999))
            ->take(5)
            ->values();

        $karteileichen = $snapshots
            ->filter(fn ($s) => (int) $s->confidence_score <= 25)
            ->sortBy('confidence_score')
            ->take(5)
            ->values();

        $snapshotStand = $snapshots->max('taken_at');

        // ── HEUTE (Live aus Tasks) ──
        $todayStart = now()->startOfDay();
        $nowTs = now();

        // Projekt-IDs des Teams (fuer Scope der Live-Counts — auch personal Tasks ohne Projekt zaehlen mit user/team)
        $projectIdsOfTeam = DB::table('planner_projects')
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->pluck('id');

        $taskScope = fn ($q) => $q->where(function ($q) use ($team, $projectIdsOfTeam) {
            $q->where('team_id', $team->id)
              ->orWhereIn('project_id', $projectIdsOfTeam);
        });

        $tasksDoneToday = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_done', true)
            ->where('done_at', '>=', $todayStart)
            ->count();

        $tasksOverdueAll = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $nowTs)
            ->count();

        $tasksNewOverdueToday = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$todayStart, $nowTs])
            ->count();

        $newFrogsToday = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_frog', true)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $minutesLoggedToday = (int) DB::table('organization_time_entries')
            ->where('team_id', $team->id)
            ->whereNull('deleted_at')
            ->whereDate('work_date', $todayStart->toDateString())
            ->sum('minutes');

        // ── Workload Top-3 ──
        $workload = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_done', false)
            ->whereNotNull('user_in_charge_id')
            ->select('user_in_charge_id', DB::raw('COUNT(*) as open_count'),
                DB::raw('SUM(CASE WHEN due_date < NOW() THEN 1 ELSE 0 END) as overdue_count'),
                DB::raw('SUM(CASE WHEN is_frog = 1 THEN 1 ELSE 0 END) as frog_count'))
            ->groupBy('user_in_charge_id')
            ->orderByDesc('open_count')
            ->limit(3)
            ->get();

        if ($workload->isNotEmpty()) {
            $userNames = \Platform\Core\Models\User::whereIn('id', $workload->pluck('user_in_charge_id'))->pluck('name', 'id');
            $workload = $workload->map(function ($row) use ($userNames) {
                return (object) [
                    'user_id' => $row->user_in_charge_id,
                    'name' => $userNames[$row->user_in_charge_id] ?? ('User #' . $row->user_in_charge_id),
                    'open' => (int) $row->open_count,
                    'overdue' => (int) $row->overdue_count,
                    'frogs' => (int) $row->frog_count,
                ];
            });
        }

        // ── Letzte 5 Bewegungen (Activity-Ticker) ──
        $recentDone = PlannerTask::query()
            ->tap($taskScope)
            ->where('is_done', true)
            ->whereNotNull('done_at')
            ->where('done_at', '>=', $nowTs->copy()->subHours(12))
            ->with('project:id,name')
            ->orderByDesc('done_at')
            ->limit(5)
            ->get(['id', 'title', 'project_id', 'user_in_charge_id', 'done_at']);

        return view('planner::livewire.ops-room', [
            'team' => $team,
            'totalProjects' => $total,
            'byColor' => $byColor,
            'brennt' => $brennt,
            'karteileichen' => $karteileichen,
            'snapshotStand' => $snapshotStand,
            // live
            'tasksDoneToday' => $tasksDoneToday,
            'tasksOverdueAll' => $tasksOverdueAll,
            'tasksNewOverdueToday' => $tasksNewOverdueToday,
            'newFrogsToday' => $newFrogsToday,
            'minutesLoggedToday' => $minutesLoggedToday,
            'workload' => $workload,
            'recentDone' => $recentDone,
            'nowIso' => $nowTs->toIso8601String(),
        ]);
    }
}
