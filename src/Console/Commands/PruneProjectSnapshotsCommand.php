<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectSnapshot;

/**
 * Retention fuer Projekt-Snapshots.
 *
 * Loescht Health-Snapshots von Projekten, die seit laenger als --days
 * SOFT-DELETED sind (also ueber die uebliche Restore-Frist hinaus).
 *
 * Bewusst NICHT im nightly Build-Command mit-erledigt:
 *  - Aufbauen und Aufraeumen sind getrennte Concerns.
 *  - Soft-deletete Projekte sind wiederherstellbar; ihre Historie soll
 *    innerhalb der Retention-Frist erhalten bleiben.
 *  - Hard-Delete braucht das gar nicht: der FK ist cascadeOnDelete, die
 *    Snapshots (inkl. slots/frogs/people) verschwinden automatisch mit.
 *
 * Erledigte (done) Projekte werden NIE angefasst — deren Historie bleibt.
 */
class PruneProjectSnapshotsCommand extends Command
{
    protected $signature = 'planner:prune-project-snapshots
                            {--days=90 : Snapshots von Projekten loeschen, die laenger als N Tage soft-deleted sind}
                            {--project= : Optional auf eine einzelne (soft-deletete) Projekt-ID beschraenken}
                            {--team= : Optional auf ein Team beschraenken}
                            {--dry-run : Nur anzeigen, was geloescht wuerde — nichts aendern}';

    protected $description = 'Retention: entfernt Snapshots von Projekten, die > N Tage soft-deleted sind (Child-Tabellen via Cascade).';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        // Nur soft-deletete Projekte, deren Loeschung ueber die Retention-Frist hinaus ist.
        $query = PlannerProject::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff);

        if ($projectId = $this->option('project')) {
            $query->where('id', $projectId);
        }
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $projects = $query->get(['id', 'name', 'team_id', 'deleted_at']);

        if ($projects->isEmpty()) {
            $this->info("Keine Projekte, die seit > {$days} Tagen soft-deleted sind. Nichts zu tun.");
            return self::SUCCESS;
        }

        $projectIds = $projects->pluck('id')->all();

        $snapshotCount = PlannerProjectSnapshot::whereIn('project_id', $projectIds)->count();

        $this->info(sprintf(
            '%s%d soft-deletete Projekt(e) (> %d Tage) mit insgesamt %d Snapshot(s).',
            $dryRun ? '[DRY-RUN] ' : '',
            $projects->count(),
            $days,
            $snapshotCount,
        ));

        foreach ($projects as $project) {
            $n = PlannerProjectSnapshot::where('project_id', $project->id)->count();
            $this->line(sprintf(
                '  %s #%d %s — geloescht am %s — %d Snapshot(s)',
                $dryRun ? '·' : '✓',
                $project->id,
                mb_substr((string) ($project->name ?? '—'), 0, 60),
                optional($project->deleted_at)->toDateString() ?? '?',
                $n,
            ));
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] Es wurde nichts geloescht. {$snapshotCount} Snapshot(s) waeren betroffen.");
            return self::SUCCESS;
        }

        if ($snapshotCount === 0) {
            $this->info('Keine Snapshots zu loeschen.');
            return self::SUCCESS;
        }

        // Bulk-Delete; slots/frogs/people gehen per DB-Cascade (cascadeOnDelete) mit.
        $deleted = PlannerProjectSnapshot::whereIn('project_id', $projectIds)->delete();

        $this->info("Fertig: {$deleted} Snapshot(s) geloescht (Child-Zeilen via Cascade).");

        Log::info('[planner:prune-project-snapshots] Snapshots aufgeraeumt', [
            'days' => $days,
            'projects' => $projectIds,
            'deleted_snapshots' => $deleted,
        ]);

        return self::SUCCESS;
    }
}
