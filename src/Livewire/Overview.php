<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Models\PlannerProject;
use Carbon\Carbon;

class Overview extends Component
{
    public $selectedDate = null;
    public $currentMonth = null;
    public $currentYear = null;

    public function mount()
    {
        $this->selectedDate = now();
        $this->currentMonth = now()->month;
        $this->currentYear = now()->year;
    }

    public function previousMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $date->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
    }

    public function nextMonth()
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $date->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
    }

    public function selectDate($date)
    {
        $this->selectedDate = Carbon::parse($date);
    }

    public function render()
    {
        $user = Auth::user();
        $teamId = $user->current_team_id;

        // Alle Projekte, bei denen der User Zugriff hat
        $accessibleProjects = PlannerProject::query()
            ->where('team_id', $teamId)
            ->where(function ($query) use ($user) {
                // 1. Projekte, bei denen der User Mitglied ist
                $query->whereHas('projectUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                // 2. ODER Projekte, bei denen der User Aufgaben hat
                ->orWhereHas('tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id);
                })
                ->orWhereHas('projectSlots.tasks', function ($q) use ($user) {
                    $q->where('user_in_charge_id', $user->id);
                });
            })
            ->pluck('id');

        // Alle Aufgaben aus diesen Projekten
        $allTasks = PlannerTask::query()
            ->whereIn('project_id', $accessibleProjects)
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->with(['project', 'userInCharge', 'user'])
            ->orderBy('due_date')
            ->get();

        // Aufgaben nach ausgewähltem Datum filtern (falls ein Datum ausgewählt ist)
        $tasks = $allTasks;
        if ($this->selectedDate) {
            $selectedDateStr = $this->selectedDate->format('Y-m-d');
            $tasks = $allTasks->filter(function ($task) use ($selectedDateStr) {
                return $task->due_date->format('Y-m-d') === $selectedDateStr;
            });
        }

        // Aufgaben pro Tag für Kalender
        $tasksByDate = $allTasks->groupBy(function ($task) {
            return $task->due_date->format('Y-m-d');
        });

        // Kalender-Daten generieren
        $calendarData = $this->generateCalendarData($tasksByDate);

        return view('planner::livewire.overview', [
            'tasks' => $tasks,
            'calendarData' => $calendarData,
            'selectedDate' => $this->selectedDate,
        ])->layout('platform::layouts.app');
    }

    private function generateCalendarData($tasksByDate = null)
    {
        $date = Carbon::create($this->currentYear, $this->currentMonth, 1);
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        
        // Erster Tag des Monats (Wochentag: 0=Sonntag, 1=Montag, etc.)
        $firstDayOfWeek = $startOfMonth->dayOfWeek;
        // Umstellen auf Montag = 0
        $firstDayOfWeek = ($firstDayOfWeek + 6) % 7;
        
        // Letzter Tag des Monats
        $lastDay = $endOfMonth->day;
        
        // Tage vom vorherigen Monat
        $days = [];
        $prevMonth = $startOfMonth->copy()->subMonth();
        $daysInPrevMonth = $prevMonth->daysInMonth;
        
        // Vormonat-Tage
        for ($i = $firstDayOfWeek - 1; $i >= 0; $i--) {
            $dayDate = $prevMonth->copy()->day($daysInPrevMonth - $i);
            $dateStr = $dayDate->format('Y-m-d');
            $days[] = [
                'date' => $dayDate,
                'isCurrentMonth' => false,
                'isToday' => false,
                'isSelected' => false,
                'taskCount' => $tasksByDate && $tasksByDate->has($dateStr) ? $tasksByDate->get($dateStr)->count() : 0,
            ];
        }
        
        // Aktuelle Monat-Tage
        for ($day = 1; $day <= $lastDay; $day++) {
            $dayDate = $startOfMonth->copy()->day($day);
            $dateStr = $dayDate->format('Y-m-d');
            $days[] = [
                'date' => $dayDate,
                'isCurrentMonth' => true,
                'isToday' => $dayDate->isToday(),
                'isSelected' => $this->selectedDate && $dayDate->format('Y-m-d') === $this->selectedDate->format('Y-m-d'),
                'taskCount' => $tasksByDate && $tasksByDate->has($dateStr) ? $tasksByDate->get($dateStr)->count() : 0,
            ];
        }
        
        // Nächster Monat - fülle bis 6 Wochen (42 Tage)
        $nextMonth = $endOfMonth->copy()->addMonth()->startOfMonth();
        $remainingDays = 42 - count($days);
        for ($day = 1; $day <= $remainingDays; $day++) {
            $dayDate = $nextMonth->copy()->day($day);
            $dateStr = $dayDate->format('Y-m-d');
            $days[] = [
                'date' => $dayDate,
                'isCurrentMonth' => false,
                'isToday' => false,
                'isSelected' => false,
                'taskCount' => $tasksByDate && $tasksByDate->has($dateStr) ? $tasksByDate->get($dateStr)->count() : 0,
            ];
        }
        
        return [
            'monthName' => $date->locale('de')->monthName,
            'year' => $date->year,
            'days' => $days,
        ];
    }
}

