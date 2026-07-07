<?php

namespace Platform\Planner\Tools;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Diagnose fuer die naechtlichen Health-Snapshot-Systeme.
 *
 * Prueft alle drei Snapshot-Systeme (Planner-Projekte, Helpdesk-Boards,
 * Dev-Packages) einheitlich ueber ihre gemeinsame HealthSnapshotSchema-Basis
 * (taken_on, taken_at, trigger, team_id) und beantwortet die Frage:
 * "Laeuft der naechtliche Snapshot-Cron ueberhaupt?"
 *
 * Read-only. Standardmaessig global (ueber alle Teams), da der Cron fuer
 * alle Teams schreibt — der wahrste Indikator, ob der Scheduler lebt.
 */
class SnapshotSystemsDiagnoseTool implements ToolContract, ToolMetadataContract
{
    /** @var array<int, array<string, string>> */
    private const SYSTEMS = [
        [
            'key' => 'planner_projects',
            'table' => 'planner_project_snapshots',
            'entity_table' => 'planner_projects',
            'command' => 'planner:build-project-snapshots',
            'scheduled_at' => '03:00 taeglich',
        ],
        [
            'key' => 'helpdesk_boards',
            'table' => 'helpdesk_board_snapshots',
            'entity_table' => 'helpdesk_boards',
            'command' => 'helpdesk:build-board-snapshots',
            'scheduled_at' => '03:15 taeglich',
        ],
        [
            'key' => 'dev_packages',
            'table' => 'dev_package_snapshots',
            'entity_table' => 'dev_packages',
            'command' => 'dev:build-package-snapshots',
            'scheduled_at' => '03:30 taeglich',
        ],
    ];

    public function getName(): string
    {
        return 'planner.snapshot_systems.diagnose';
    }

    public function getDescription(): string
    {
        return 'GET /snapshot-systems/diagnose - Diagnose der naechtlichen Health-Snapshot-Cronjobs (Planner-Projekte, Helpdesk-Boards, Dev-Packages). Zeigt pro System letzten Snapshot, Cron-Aktivitaet der letzten 7 Tage und ein Gesamturteil, ob der Scheduler laeuft. scope=global (default) oder scope=team. days=N Fenster (default 7).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scope' => [
                    'type' => 'string',
                    'enum' => ['global', 'team'],
                    'description' => 'global = ueber alle Teams (default, bester Cron-Indikator). team = nur aktuelles Team.',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Fenster fuer die Aktivitaets-Zaehlung in Tagen (1..90). Default 7.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (! $context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $scope = ($arguments['scope'] ?? 'global') === 'team' ? 'team' : 'global';
            $days = max(1, min(90, (int) ($arguments['days'] ?? 7)));

            $teamId = null;
            if ($scope === 'team') {
                $teamId = $context->team->id ?? null;
                if (! $teamId) {
                    return ToolResult::error('TEAM_REQUIRED', 'scope=team, aber kein Team im Kontext.');
                }
            }

            $today = Carbon::now()->toDateString();
            $windowFrom = Carbon::now()->subDays($days - 1)->toDateString();
            $yesterday = Carbon::now()->subDay()->toDateString();

            $results = [];
            $anySchedulerRecent = false;

            foreach (self::SYSTEMS as $sys) {
                if (! Schema::hasTable($sys['table'])) {
                    $results[$sys['key']] = [
                        'status' => 'missing_table',
                        'note' => "Tabelle {$sys['table']} existiert nicht (Modul nicht migriert/installiert?).",
                        'command' => $sys['command'],
                        'scheduled_at' => $sys['scheduled_at'],
                    ];
                    continue;
                }

                $base = DB::table($sys['table']);
                if ($teamId !== null) {
                    $base->where('team_id', $teamId);
                }

                $total = (clone $base)->count();
                $latestOn = (clone $base)->max('taken_on');
                $latestAt = (clone $base)->max('taken_at');

                // Cron-spezifische Signale
                $cronBase = (clone $base)->where('trigger', 'cron');
                $cronLatestOn = (clone $cronBase)->max('taken_on');
                $cronDaysInWindow = (clone $cronBase)
                    ->where('taken_on', '>=', $windowFrom)
                    ->distinct()
                    ->count('taken_on');

                $todayRows = (clone $base)->where('taken_on', $today)->count();

                // Erwartete aktive Entities (soft-delete-aware, falls Spalte existiert)
                $expected = null;
                if (Schema::hasTable($sys['entity_table'])) {
                    $eq = DB::table($sys['entity_table']);
                    if (Schema::hasColumn($sys['entity_table'], 'deleted_at')) {
                        $eq->whereNull('deleted_at');
                    }
                    if ($teamId !== null && Schema::hasColumn($sys['entity_table'], 'team_id')) {
                        $eq->where('team_id', $teamId);
                    }
                    $expected = $eq->count();
                }

                $daysSinceCron = $cronLatestOn
                    ? Carbon::parse($cronLatestOn)->startOfDay()->diffInDays(Carbon::now()->startOfDay())
                    : null;

                // Verdict pro System
                if ($total === 0) {
                    $status = 'never_ran';
                } elseif ($cronLatestOn === null) {
                    $status = 'cron_never_ran'; // es gibt nur manuelle Snapshots
                } elseif (in_array($cronLatestOn, [$today, $yesterday], true)) {
                    $status = 'ok';
                    $anySchedulerRecent = true;
                } else {
                    $status = 'stale';
                }

                $results[$sys['key']] = [
                    'status' => $status,
                    'command' => $sys['command'],
                    'scheduled_at' => $sys['scheduled_at'],
                    'total_snapshots' => $total,
                    'latest_taken_on' => $latestOn,
                    'latest_taken_at' => $latestAt,
                    'cron_latest_taken_on' => $cronLatestOn,
                    'days_since_last_cron' => $daysSinceCron,
                    "cron_days_in_last_{$days}d" => $cronDaysInWindow,
                    'expected_days_in_window' => $days,
                    'today_snapshots' => $todayRows,
                    'active_entities' => $expected,
                    'today_coverage' => ($expected && $expected > 0)
                        ? round($todayRows / $expected * 100) . '%'
                        : null,
                ];
            }

            // Gesamturteil
            if ($anySchedulerRecent) {
                $overall = 'scheduler_alive';
                $summary = 'Mindestens ein Snapshot-System hat heute/gestern einen Cron-Snapshot — der Scheduler laeuft.';
            } else {
                $tables = array_filter(self::SYSTEMS, fn ($s) => Schema::hasTable($s['table']));
                $overall = count($tables) === 0 ? 'no_tables' : 'scheduler_likely_down';
                $summary = $overall === 'no_tables'
                    ? 'Keine Snapshot-Tabellen gefunden.'
                    : 'KEIN System hat einen Cron-Snapshot von heute oder gestern. Der naechtliche Scheduler (php artisan schedule:run) laeuft vermutlich nicht. Pruefen: crontab -l | grep schedule:run und storage/logs/*-snapshots.log.';
            }

            return ToolResult::success([
                'scope' => $scope,
                'team_id' => $teamId,
                'checked_at' => Carbon::now()->toDateTimeString(),
                'window_days' => $days,
                'overall' => $overall,
                'summary' => $summary,
                'systems' => $results,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['planner', 'helpdesk', 'dev', 'snapshot', 'diagnose', 'ops', 'cron', 'health'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
