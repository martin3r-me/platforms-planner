<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Carbon\Carbon;

class Dashboard extends Component
{
    public $perspective = 'team';

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => 'Platform\Planner\Models\PlannerProject',
            'modelId' => null,
            'subject' => 'Planner Dashboard',
            'description' => 'Übersicht aller Projekte und Aufgaben',
            'url' => route('planner.dashboard'),
            'source' => 'planner.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
                'perspective' => $this->perspective,
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // === PROJEKTE (nur Team-Projekte) ===
        $projects = PlannerProject::where('team_id', $team->id)->orderBy('name')->get();
        $activeProjects = $projects->filter(function($project) {
            return $project->is_active === null || $project->is_active === true;
        })->count();
        $totalProjects = $projects->count();

        if ($this->perspective === 'personal') {
            // === PERSÖNLICHE AUFGABEN ===
            $myTasks = PlannerTask::query()
                ->where(function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->whereNull('project_id')
                          ->where('user_id', $user->id); // private Aufgaben
                    })->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $user->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id') // zuständige Projektaufgabe im Project-Slot
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                                   });
                          });
                    });
                })
                ->where('team_id', $team->id)
                ->get();

            $openTasks = $myTasks->where('is_done', false)->count();
            $completedTasks = $myTasks->where('is_done', true)->count();
            $totalTasks = $myTasks->count();
            $frogTasks = $myTasks->where('is_frog', true)->count();
            $overdueTasks = $myTasks->where('is_done', false)
                ->filter(fn($task) => $task->due_date && $task->due_date->isPast())
                ->count();

            // === PERSÖNLICHE STORY POINTS ===
            $totalStoryPoints = $myTasks->sum(fn($task) => $task->story_points?->points() ?? 0);
            $completedStoryPoints = $myTasks->where('is_done', true)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
            $openStoryPoints = $myTasks->where('is_done', false)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            // === PERSÖNLICHE MONATLICHE PERFORMANCE ===
            $monthlyCreatedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->whereNull('project_id')
                          ->where('user_id', $user->id);
                    })->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $user->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->count();

            $monthlyCompletedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->whereNull('project_id')
                          ->where('user_id', $user->id);
                    })->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $user->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->count();

            $monthlyCreatedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->whereNull('project_id')
                          ->where('user_id', $user->id);
                    })->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $user->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            $monthlyCompletedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($user) {
                    $q->where(function ($q) use ($user) {
                        $q->whereNull('project_id')
                          ->where('user_id', $user->id);
                    })->orWhere(function ($q) use ($user) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $user->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

        } else {
            // === TEAM-AUFGABEN ===
            $teamTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id') // Project-Slot Aufgaben
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id'); // oder ohne Slot-Zuordnung (Backlog)
                      });
                })
                ->get();

            $openTasks = $teamTasks->where('is_done', false)->count();
            $completedTasks = $teamTasks->where('is_done', true)->count();
            $totalTasks = $teamTasks->count();
            $frogTasks = $teamTasks->where('is_frog', true)->count();
            $overdueTasks = $teamTasks->where('is_done', false)
                ->filter(fn($task) => $task->due_date && $task->due_date->isPast())
                ->count();

            // === TEAM STORY POINTS ===
            $totalStoryPoints = $teamTasks->sum(fn($task) => $task->story_points?->points() ?? 0);
            $completedStoryPoints = $teamTasks->where('is_done', true)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
            $openStoryPoints = $teamTasks->where('is_done', false)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            // === TEAM MONATLICHE PERFORMANCE ===
            $monthlyCreatedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->count();

            $monthlyCompletedTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->count();

            $monthlyCreatedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('created_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            $monthlyCompletedPoints = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) {
                    $q->whereNotNull('project_slot_id')
                      ->orWhere(function ($slotQ) {
                          $slotQ->whereNull('project_slot_id')
                                ->whereNull('sprint_slot_id');
                      });
                })
                ->whereDate('done_at', '>=', $startOfMonth)
                ->get()
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
        }

        // === TEAM-MITGLIEDER-ÜBERSICHT ===
        $teamMembers = $team->users()->get()->map(function ($member) use ($team) {
            // Alle Aufgaben des Team-Mitglieds (private + zuständige Projektaufgaben)
            $memberTasks = PlannerTask::query()
                ->where('team_id', $team->id)
                ->where(function ($q) use ($member) {
                    $q->where(function ($q) use ($member) {
                        $q->whereNull('project_id')
                          ->where('user_id', $member->id); // private Aufgaben
                    })->orWhere(function ($q) use ($member) {
                        $q->whereNotNull('project_id')
                          ->where('user_in_charge_id', $member->id)
                          ->where(function ($subQ) {
                              $subQ->whereNotNull('project_slot_id')
                                   ->orWhere(function ($slotQ) {
                                       $slotQ->whereNull('project_slot_id')
                                             ->whereNull('sprint_slot_id');
                                   });
                          });
                    });
                })
                ->get();

            $openTasks = $memberTasks->where('is_done', false)->count();
            $completedTasks = $memberTasks->where('is_done', true)->count();
            $totalTasks = $memberTasks->count();
            $totalStoryPoints = $memberTasks->sum(fn($task) => $task->story_points?->points() ?? 0);
            $completedStoryPoints = $memberTasks->where('is_done', true)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);
            $openStoryPoints = $memberTasks->where('is_done', false)
                ->sum(fn($task) => $task->story_points?->points() ?? 0);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'profile_photo_url' => $member->profile_photo_url,
                'open_tasks' => $openTasks,
                'completed_tasks' => $completedTasks,
                'total_tasks' => $totalTasks,
                'total_story_points' => $totalStoryPoints,
                'completed_story_points' => $completedStoryPoints,
                'open_story_points' => $openStoryPoints,
            ];
        })->sortByDesc('open_tasks');

        // === PROJEKT-ÜBERSICHT (nur aktive Projekte) ===
        $perspective = $this->perspective;
        $activeProjectsList = $projects->filter(function($project) {
            return $project->is_active === null || $project->is_active === true;
        })
        ->map(function ($project) use ($user, $perspective) {
            if ($perspective === 'personal') {
                $projectTasks = PlannerTask::where('project_id', $project->id)
                    ->where('user_in_charge_id', $user->id)
                    ->where(function ($q) {
                        $q->whereNotNull('project_slot_id')
                          ->orWhere(function ($slotQ) {
                              $slotQ->whereNull('project_slot_id')
                                    ->whereNull('sprint_slot_id');
                          });
                    })
                    ->get();
            } else {
                $projectTasks = PlannerTask::where('project_id', $project->id)
                    ->where(function ($q) {
                        $q->whereNotNull('project_slot_id')
                          ->orWhere(function ($slotQ) {
                              $slotQ->whereNull('project_slot_id')
                                    ->whereNull('sprint_slot_id');
                          });
                    })
                    ->get();
            }
            
            return [
                'id' => $project->id,
                'name' => $project->name,
                'open_tasks' => $projectTasks->where('is_done', false)->count(),
                'total_tasks' => $projectTasks->count(),
                'story_points' => $projectTasks->sum(fn($task) => $task->story_points?->points() ?? 0),
            ];
        })
        ->sortByDesc('open_tasks')
        ->take(5);

        // Additional properties for sidebar
        $todayCreatedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('created_at', today())
            ->count();
            
        $todayCompletedTasks = PlannerTask::where('team_id', $team->id)
            ->whereDate('done_at', today())
            ->count();

        return view('planner::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'currentDay' => now()->format('l'),
            'perspective' => $this->perspective,
            'activeProjects' => $activeProjects,
            'totalProjects' => $totalProjects,
            'openTasks' => $openTasks,
            'completedTasks' => $completedTasks,
            'totalTasks' => $totalTasks,
            'frogTasks' => $frogTasks,
            'overdueTasks' => $overdueTasks,
            'totalStoryPoints' => $totalStoryPoints,
            'completedStoryPoints' => $completedStoryPoints,
            'openStoryPoints' => $openStoryPoints,
            'monthlyCreatedTasks' => $monthlyCreatedTasks,
            'monthlyCompletedTasks' => $monthlyCompletedTasks,
            'monthlyCreatedPoints' => $monthlyCreatedPoints,
            'monthlyCompletedPoints' => $monthlyCompletedPoints,
            'teamMembers' => $teamMembers,
            'activeProjectsList' => $activeProjectsList,
            'todayCreatedTasks' => $todayCreatedTasks,
            'todayCompletedTasks' => $todayCompletedTasks,
        ])->layout('platform::layouts.app');
    }
}