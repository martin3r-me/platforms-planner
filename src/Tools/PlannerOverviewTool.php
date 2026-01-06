<?php

namespace Platform\Planner\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

/**
 * Tool zum Abrufen einer Übersicht über Planner-Konzepte und Beziehungen
 * 
 * Gibt eine strukturierte Übersicht über alle Konzepte im Planner-Modul zurück:
 * - Aufgaben (Tasks) und ihre verschiedenen Typen
 * - Slots und ihre Beziehungen
 * - Backlog-Konzept
 * - Persönliche Aufgaben und Task Groups
 * - Beziehungen zwischen allen Konzepten
 */
class PlannerOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'planner.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /planner/overview - Zeigt Übersicht über Planner-Konzepte und Beziehungen. EMPFOHLEN: Nutze dieses Tool, wenn du die Struktur des Planner-Moduls verstehen möchtest (Aufgaben, Slots, Backlog, persönliche Aufgaben, Task Groups). REST-Parameter: keine.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'planner',
                'scope' => [
                    'team_based' => [
                        'description' => 'Projekte sind IMMER team-bezogen',
                        'projects' => [
                            'team_required' => true,
                            'team_id' => 'Projekte benötigen immer ein team_id (aus Kontext oder explizit angegeben)',
                            'access' => 'Nur User, die dem Team angehören, können auf Projekte zugreifen',
                        ],
                        'project_tasks' => [
                            'team_inheritance' => 'Aufgaben in Projekten erben das Team vom Projekt (über project_id)',
                            'team_id' => 'Wird automatisch vom Projekt übernommen',
                        ],
                    ],
                    'user_based' => [
                        'description' => 'Persönliche Aufgaben sind NICHT team-bezogen',
                        'personal_tasks' => [
                            'team_required' => false,
                            'user_id' => 'Persönliche Aufgaben gehören nur zum User (user_id)',
                            'team_id' => 'Kann vorhanden sein, ist aber nicht erforderlich',
                            'access' => 'Nur der User selbst kann auf seine persönlichen Aufgaben zugreifen',
                        ],
                    ],
                ],
                'concepts' => [
                    'tasks' => [
                        'description' => 'Aufgaben (Tasks) können in Projekt-Slots oder persönlich sein',
                        'types' => [
                            'project_task' => [
                                'description' => 'Aufgabe in Projekt-Slot',
                                'conditions' => 'project_id vorhanden + project_slot_id vorhanden',
                                'example' => 'Aufgabe ist einem Slot in einem Projekt zugeordnet',
                            ],
                            'backlog_task' => [
                                'description' => 'Backlog-Aufgabe (Projekt-Bezug, aber ohne Slot)',
                                'conditions' => 'project_id vorhanden + project_slot_id = null',
                                'example' => 'Aufgabe gehört zu einem Projekt, ist aber noch keinem Slot zugeordnet',
                                'how_enters' => 'Wenn project_slot_id auf null gesetzt wird oder Slot gelöscht wird',
                            ],
                            'personal_task' => [
                                'description' => 'Persönliche Aufgabe in Task Group',
                                'conditions' => 'project_id = null + task_group_id vorhanden',
                                'example' => 'Persönliche Aufgabe, die einer Task Group zugeordnet ist',
                            ],
                            'personal_backlog' => [
                                'description' => 'Persönliche Backlog-Aufgabe',
                                'conditions' => 'project_id = null + task_group_id = null',
                                'example' => 'Persönliche Aufgabe ohne Projekt- und Task-Group-Bezug',
                            ],
                        ],
                    ],
                    'slots' => [
                        'description' => 'Projekt-Slots strukturieren Aufgaben zeitlich/strukturell (z.B. Sprint/Phase/Swimlane)',
                        'relation' => 'Slot → Projekt (belongsTo), Slot → Tasks (hasMany)',
                        'purpose' => 'Ermöglicht klare Zuordnung von Aufgaben zu Phasen/Sprints innerhalb eines Projekts',
                    ],
                    'backlog' => [
                        'description' => 'Backlog = Aufgaben mit Projekt-Bezug, aber ohne Slot-Zuordnung',
                        'how_tasks_enter' => [
                            'Wenn project_slot_id auf null gesetzt wird',
                            'Wenn ein Slot gelöscht wird (Aufgaben werden ins Backlog verschoben)',
                            'Wenn eine Aufgabe mit project_id, aber ohne project_slot_id erstellt wird',
                        ],
                        'project_backlog' => 'Aufgaben mit project_id, aber project_slot_id = null',
                        'personal_backlog' => 'Aufgaben ohne project_id und ohne task_group_id',
                    ],
                    'task_groups' => [
                        'description' => 'Task Groups = persönliche Slots (äquivalent zu Projekt-Slots, aber für persönliche Aufgaben)',
                        'relation' => 'Task Group → Tasks (hasMany)',
                        'purpose' => 'Gruppiert thematisch/organisatorisch persönliche Aufgaben (z.B. in Backlogs oder Bereichen)',
                    ],
                ],
                'relationships' => [
                    'project_hierarchy' => 'Projekt → Slots → Tasks',
                    'personal_hierarchy' => 'User → Task Groups → Tasks',
                    'task_movement' => 'Aufgaben können zwischen Projekt und persönlich verschoben werden',
                    'backlog_flow' => [
                        'project_backlog' => 'Aufgaben mit Projekt, aber ohne Slot',
                        'personal_backlog' => 'Aufgaben ohne Projekt und ohne Task Group',
                    ],
                ],
                'workflows' => [
                    'creating_project_task' => [
                        'step_1' => 'Erstelle Projekt (planner.projects.POST)',
                        'step_2' => 'Erstelle Slot im Projekt (planner.project_slots.POST)',
                        'step_3' => 'Erstelle Aufgabe mit project_id und project_slot_id (planner.tasks.POST)',
                    ],
                    'creating_personal_task' => [
                        'step_1' => 'Erstelle Task Group (optional, für Gruppierung)',
                        'step_2' => 'Erstelle Aufgabe ohne project_id (planner.tasks.POST)',
                        'step_3' => 'Optional: Weise task_group_id zu',
                    ],
                    'moving_to_backlog' => [
                        'project_task' => 'Setze project_slot_id auf null → Aufgabe wird ins Projekt-Backlog verschoben',
                        'personal_task' => 'Setze task_group_id auf null → Aufgabe wird ins persönliche Backlog verschoben',
                    ],
                ],
                'related_tools' => [
                    'projects' => [
                        'list' => 'planner.projects.GET - Listet alle Projekte auf',
                        'metrics' => 'planner.projects.metrics.GET - Aggregierte Projekt-Metriken (Tasks, Story Points, Minuten) + Ranking nach Komplexität',
                        'get' => 'planner.project.GET - Ruft einzelnes Projekt mit vollständiger Struktur ab',
                        'create' => 'planner.projects.POST - Erstellt neues Projekt',
                    ],
                    'slots' => [
                        'list' => 'planner.project_slots.GET - Listet Slots eines Projekts auf',
                        'get' => 'planner.project_slot.GET - Ruft einzelnen Slot mit vollständiger Struktur ab',
                        'create' => 'planner.project_slots.POST - Erstellt neuen Slot in Projekt',
                    ],
                    'tasks' => [
                        'list' => 'planner.tasks.GET - Listet Aufgaben auf (filterbar nach Projekt, Slot, User)',
                        'create' => 'planner.tasks.POST - Erstellt neue Aufgabe',
                        'update' => 'planner.tasks.PUT - Aktualisiert Aufgabe (kann zwischen Projekt/Slot verschoben werden)',
                        'bulk_create' => 'planner.tasks.bulk.POST - Erstellt mehrere Aufgaben in einem Request (Batch-Anlage)',
                        'bulk_update' => 'planner.tasks.bulk.PUT - Aktualisiert mehrere Aufgaben in einem Request (Batch-Operation)',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Planner-Übersicht: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['overview', 'help', 'planner', 'concepts', 'structure'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
            'recommended_for' => 'Verstehen der Modul-Struktur und Beziehungen zwischen Konzepten',
        ];
    }
}

