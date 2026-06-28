<?php

namespace Platform\Planner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Services\ProjectSnapshotService;

class BuildProjectSnapshotsCommand extends Command
{
    protected $signature = 'planner:build-project-snapshots
                            {--project= : Optional einzelne Projekt-ID}
                            {--team= : Optional auf ein Team beschraenken}
                            {--trigger=cron : Snapshot-Trigger-Label (cron|manual|backfill)}';

    protected $description = 'Erstellt fuer alle nicht-soft-deleted Projekte einen Tages-Snapshot (max 1/Tag/Projekt).';

    public function handle(ProjectSnapshotService $service): int
    {
        $query = PlannerProject::query();

        if ($projectId = $this->option('project')) {
            $query->where('id', $projectId);
        }
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $trigger = (string) ($this->option('trigger') ?? 'cron');

        $projects = $query->get();
        $total = $projects->count();

        if ($total === 0) {
            $this->info('Keine Projekte gefunden.');
            return self::SUCCESS;
        }

        $this->info("Snapshotte {$total} Projekt(e) — Trigger: {$trigger}");

        $ok = 0;
        $failed = 0;

        foreach ($projects as $project) {
            try {
                $snapshot = $service->snapshot($project, $trigger);
                $ok++;
                $this->line(sprintf(
                    '  ✓ #%d %s — health=%s (%s), confidence=%d',
                    $project->id,
                    mb_substr((string) ($project->name ?? '—'), 0, 60),
                    $snapshot->health_score ?? '–',
                    $snapshot->health_color ?? 'gray',
                    $snapshot->confidence_score,
                ));
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf(
                    '  ✗ #%d %s — %s',
                    $project->id,
                    mb_substr((string) ($project->name ?? '—'), 0, 60),
                    $e->getMessage(),
                ));
                Log::error('[planner:build-project-snapshots] Snapshot fehlgeschlagen', [
                    'project_id' => $project->id,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Fertig: {$ok} OK, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
