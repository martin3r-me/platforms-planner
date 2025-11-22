<?php

namespace Platform\Planner\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Planner\Models\PlannerProject;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Datawarehouse API Controller für Projects
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams).
 */
class ProjectDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Projects
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = PlannerProject::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'created_at', 'updated_at', 'done_at', 'name'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // Team-Relation laden für Team-Name
        $query->with('team:id,name');
        $projects = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
                'description' => $project->description ?? null,
                'team_id' => $project->team_id,
                'team_name' => $project->team?->name, // Team-Name mitliefern (denormalisiert)
                'user_id' => $project->user_id,
                'project_type' => $project->project_type?->value,
                'done' => $project->done,
                'done_at' => $project->done_at?->toIso8601String(),
                'planned_minutes' => $project->planned_minutes,
                'created_at' => $project->created_at->toIso8601String(),
                'updated_at' => $project->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $projects->setCollection($formatted),
            'Projects erfolgreich geladen'
        );
    }

    /**
     * Wendet alle Filter auf die Query an
     */
    protected function applyFilters($query, Request $request): void
    {
        // Team-Filter mit Kind-Teams Option (standardmäßig aktiviert)
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            // Standardmäßig Kind-Teams inkludieren (wenn nicht explizit false)
            // Unterstützt String-Werte '1'/'0' und Boolean
            $includeChildrenValue = $request->input('include_child_teams');
            // Prüfe ob include_child_teams explizit gesetzt wurde (auch als '0')
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                // Team mit Kind-Teams laden
                $team = Team::find($teamId);
                
                if ($team) {
                    // Alle Team-IDs inkl. Kind-Teams sammeln
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    $query->whereIn('team_id', $teamIds);
                } else {
                    // Team nicht gefunden - leeres Ergebnis
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Nur das genannte Team (wenn explizit deaktiviert)
                $query->where('team_id', $teamId);
            }
        }
        
        // WICHTIG: Kein Standard-Filter für done - alle Projects werden zurückgegeben
        // Nur wenn explizit gefiltert wird

        // Erledigte Projekte (done)
        if ($request->has('is_done')) {
            if ($request->is_done === 'true' || $request->is_done === '1') {
                $query->where('done', true);
            } elseif ($request->is_done === 'false' || $request->is_done === '0') {
                $query->where('done', false);
            }
        }

        // Datums-Filter für done_at (heute erledigt)
        if ($request->boolean('done_today')) {
            $query->whereDate('done_at', Carbon::today());
        }

        // Datums-Range für done_at
        if ($request->has('done_from')) {
            $query->whereDate('done_at', '>=', $request->done_from);
        }
        if ($request->has('done_to')) {
            $query->whereDate('done_at', '<=', $request->done_to);
        }

        // Erstellt heute
        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Erstellt in Range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Projekt-Typ Filter
        if ($request->has('project_type')) {
            $query->where('project_type', $request->project_type);
        }
    }
}

