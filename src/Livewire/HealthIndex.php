<?php

namespace Platform\Planner\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Platform\Planner\Models\PlannerProjectSnapshot;

class HealthIndex extends Component
{
    /** @var string all|red|yellow|green|gray */
    public string $colorFilter = 'all';

    /** @var string all|strategy|progress|burn */
    public string $axisFilter = 'all';

    /** @var string all|aktiv|ruhend|abgeschlossen|verworfen */
    public string $lifecycleFilter = 'aktiv';

    /** @var string worst|best|movement|confidence|name */
    public string $sort = 'worst';

    public function rendered(): void
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerProject',
            'modelId' => null,
            'subject' => 'Health-Index',
            'description' => 'Teamweite Sicht auf den Gesundheitsstand aller Projekte',
            'url' => route('planner.health-index'),
            'source' => 'planner.health-index',
            'recipients' => [],
            'meta' => [
                'view_type' => 'health_index',
                'user_id' => Auth::id(),
            ],
        ]);
    }

    #[Layout('platform::layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        // Juengsten Snapshot pro Projekt im aktuellen Team
        $latestIds = DB::table('planner_project_snapshots as a')
            ->where('a.team_id', $team->id)
            ->whereRaw('a.taken_on = (
                SELECT MAX(b.taken_on) FROM planner_project_snapshots b
                WHERE b.project_id = a.project_id
            )')
            ->pluck('a.id');

        // color ist keine echte Spalte (HasColors-Trait via Lookup-Tabelle),
        // darf nicht im Select stehen — wir laden contextColors eager mit, damit
        // der color-Accessor ohne N+1 funktioniert.
        // whereHas('project') filtert Snapshots von soft-deleted Projekten raus.
        $all = PlannerProjectSnapshot::with([
                'project:id,name,kind,lifecycle_state',
                'project.contextColors',
            ])
            ->whereIn('id', $latestIds)
            ->whereHas('project')
            ->get();

        // ── KPIs / Verteilungen ueber den vollen Scope (vor Filter) ──
        $totalAll = $all->count();
        $byColor = [
            'red' => 0, 'yellow' => 0, 'green' => 0, 'gray' => 0,
        ];
        $byAxis = ['strategy' => 0, 'progress' => 0, 'burn' => 0];
        $byConfidence = ['high_75_100' => 0, 'medium_50_74' => 0, 'low_25_49' => 0, 'none_0_24' => 0];
        $missingLayers = ['canvas' => 0, 'planned_period' => 0, 'planned_minutes' => 0, 'tasks' => 0];

        foreach ($all as $s) {
            $byColor[$s->health_color ?: 'gray'] = ($byColor[$s->health_color ?: 'gray'] ?? 0) + 1;
            if ($s->worst_axis && isset($byAxis[$s->worst_axis])) {
                $byAxis[$s->worst_axis]++;
            }
            $c = (int) $s->confidence_score;
            if ($c >= 75) $byConfidence['high_75_100']++;
            elseif ($c >= 50) $byConfidence['medium_50_74']++;
            elseif ($c >= 25) $byConfidence['low_25_49']++;
            else $byConfidence['none_0_24']++;

            if ($s->confidence_reason && str_starts_with($s->confidence_reason, 'missing:')) {
                foreach (explode(',', substr($s->confidence_reason, strlen('missing:'))) as $m) {
                    $m = trim($m);
                    if (isset($missingLayers[$m])) {
                        $missingLayers[$m]++;
                    }
                }
            }
        }

        // ── Filter anwenden ──
        $filtered = $all;
        if ($this->colorFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => ($s->health_color ?: 'gray') === $this->colorFilter);
        }
        if ($this->axisFilter !== 'all') {
            $filtered = $filtered->filter(fn ($s) => $s->worst_axis === $this->axisFilter);
        }
        if ($this->lifecycleFilter !== 'all') {
            $filtered = $filtered->filter(
                fn ($s) => ($s->project?->lifecycle_state?->value ?? 'aktiv') === $this->lifecycleFilter
            );
        }

        // ── Sortierung ──
        $colorRank = ['red' => 0, 'yellow' => 1, 'gray' => 2, 'green' => 3];
        $filtered = match ($this->sort) {
            'best' => $filtered->sortByDesc(fn ($s) => $s->health_score ?? -1)->values(),
            'movement' => $filtered->sortByDesc(fn ($s) => $s->last_movement_at?->timestamp ?? 0)->values(),
            'confidence' => $filtered->sortBy('confidence_score')->values(),
            'name' => $filtered->sortBy(fn ($s) => mb_strtolower($s->project?->name ?? ''))->values(),
            default => $filtered->sort(function ($a, $b) use ($colorRank) {
                $ra = $colorRank[$a->health_color ?? 'gray'] ?? 9;
                $rb = $colorRank[$b->health_color ?? 'gray'] ?? 9;
                if ($ra !== $rb) return $ra <=> $rb;
                return (int) ($a->health_score ?? 999) <=> (int) ($b->health_score ?? 999);
            })->values(),
        };

        // ── Bewegung: Top-Gewinner + Top-Verlierer ueber alle Snapshots mit Delta ──
        $withDelta = $all->filter(fn ($s) => $s->delta_health_score !== null && $s->delta_health_score !== 0);
        $topGainers = $withDelta
            ->filter(fn ($s) => $s->delta_health_score > 0)
            ->sortByDesc('delta_health_score')
            ->take(5)
            ->values();
        $topLosers = $withDelta
            ->filter(fn ($s) => $s->delta_health_score < 0)
            ->sortBy('delta_health_score')
            ->take(5)
            ->values();

        $totalMovement = $withDelta->sum(fn ($s) => abs($s->delta_health_score));

        return view('planner::livewire.health-index', [
            'team' => $team,
            'totalAll' => $totalAll,
            'byColor' => $byColor,
            'byAxis' => $byAxis,
            'byConfidence' => $byConfidence,
            'missingLayers' => $missingLayers,
            'snapshots' => $filtered,
            'lastTakenOn' => $all->max('taken_on'),
            'topGainers' => $topGainers,
            'topLosers' => $topLosers,
            'movedProjectsCount' => $withDelta->count(),
            'totalMovement' => $totalMovement,
        ]);
    }
}
