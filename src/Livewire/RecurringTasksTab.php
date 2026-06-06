<?php

namespace Platform\Planner\Livewire;

use Livewire\Component;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerRecurringTask;
use Platform\Planner\Models\PlannerProjectSlot;
use Platform\Planner\Enums\TaskPriority;
use Platform\Planner\Enums\TaskStoryPoints;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class RecurringTasksTab extends Component
{
    public $project;
    public $recurringTasks = [];
    public $showCreateForm = false;
    public $editingId = null;
    
    // Form-Felder
    public $form = [
        'title' => '',
        'description' => '',
        'story_points' => null,
        'priority' => 'normal',
        'planned_minutes' => null,
        'user_in_charge_id' => null,
        'project_slot_id' => null,
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 1,
        'recurrence_end_date' => null,
        'next_due_date' => null,
        'is_active' => true,
        'auto_delete_old_tasks' => false,
        'auto_mark_as_done' => false,

        // Erweiterte Muster
        'weekday_mask' => null,
        'monthly_pattern' => null,
        'monthly_day_of_month' => null,
        'monthly_ordinal' => null,
        'monthly_weekday' => null,

        // Vorlauf / Chain / Limit
        'lead_time_days' => 0,
        'chain_on_complete' => false,
        'max_occurrences' => null,
        'skip_weekends' => false,
    ];

    public $recurrenceEndDateInput = '';
    public $nextDueDateInput = '';

    public function mount($projectId = null)
    {
        if ($projectId) {
            $this->project = PlannerProject::findOrFail($projectId);
            $this->loadRecurringTasks();
        }
    }

    #[On('project-loaded')]
    public function onProjectLoaded($projectId)
    {
        $this->project = PlannerProject::findOrFail($projectId);
        $this->loadRecurringTasks();
    }

    public function loadRecurringTasks()
    {
        if (!$this->project) {
            return;
        }

        $this->recurringTasks = PlannerRecurringTask::where('project_id', $this->project->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function openCreateForm()
    {
        $this->resetForm();
        $this->showCreateForm = true;
        $this->editingId = null;
    }

    public function openEditForm($id)
    {
        $recurringTask = PlannerRecurringTask::findOrFail($id);

        $this->form = [
            'title' => $recurringTask->title,
            'description' => $recurringTask->description,
            'story_points' => $recurringTask->story_points?->value,
            'priority' => $recurringTask->priority?->value ?? 'normal',
            'planned_minutes' => $recurringTask->planned_minutes,
            'user_in_charge_id' => $recurringTask->user_in_charge_id,
            'project_slot_id' => $recurringTask->project_slot_id,
            'recurrence_type' => $recurringTask->recurrence_type,
            'recurrence_interval' => $recurringTask->recurrence_interval,
            'recurrence_end_date' => $recurringTask->recurrence_end_date,
            'next_due_date' => $recurringTask->next_due_date,
            'is_active' => $recurringTask->is_active,
            'auto_delete_old_tasks' => $recurringTask->auto_delete_old_tasks,
            'auto_mark_as_done' => $recurringTask->auto_mark_as_done,

            'weekday_mask' => $recurringTask->weekday_mask,
            'monthly_pattern' => $recurringTask->monthly_pattern,
            'monthly_day_of_month' => $recurringTask->monthly_day_of_month,
            'monthly_ordinal' => $recurringTask->monthly_ordinal,
            'monthly_weekday' => $recurringTask->monthly_weekday,

            'lead_time_days' => (int) $recurringTask->lead_time_days,
            'chain_on_complete' => (bool) $recurringTask->chain_on_complete,
            'max_occurrences' => $recurringTask->max_occurrences,
            'skip_weekends' => (bool) $recurringTask->skip_weekends,
        ];

        $this->recurrenceEndDateInput = $recurringTask->recurrence_end_date 
            ? $recurringTask->recurrence_end_date->format('Y-m-d\TH:i') 
            : '';
        $this->nextDueDateInput = $recurringTask->next_due_date 
            ? $recurringTask->next_due_date->format('Y-m-d\TH:i') 
            : '';

        $this->showCreateForm = true;
        $this->editingId = $id;
    }

    public function closeForm()
    {
        $this->showCreateForm = false;
        $this->editingId = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->form = [
            'title' => '',
            'description' => '',
            'story_points' => null,
            'priority' => 'normal',
            'planned_minutes' => null,
            'user_in_charge_id' => null,
            'project_slot_id' => null,
            'recurrence_type' => 'weekly',
            'recurrence_interval' => 1,
            'recurrence_end_date' => null,
            'next_due_date' => null,
            'is_active' => true,
            'auto_delete_old_tasks' => false,
            'auto_mark_as_done' => false,

            'weekday_mask' => null,
            'monthly_pattern' => null,
            'monthly_day_of_month' => null,
            'monthly_ordinal' => null,
            'monthly_weekday' => null,

            'lead_time_days' => 0,
            'chain_on_complete' => false,
            'max_occurrences' => null,
            'skip_weekends' => false,
        ];
        $this->recurrenceEndDateInput = '';
        $this->nextDueDateInput = '';
    }

    /**
     * Wochentag im Bitfeld umschalten. ISO Mo=0..So=6, Bit = 2^iso.
     */
    public function toggleWeekday(int $iso): void
    {
        $bit = (int) (PlannerRecurringTask::WEEKDAY_BITS[$iso] ?? 0);
        if ($bit === 0) return;
        $current = (int) ($this->form['weekday_mask'] ?? 0);
        $this->form['weekday_mask'] = $current ^ $bit;
        if ($this->form['weekday_mask'] === 0) {
            $this->form['weekday_mask'] = null; // null = keine Einschränkung
        }
    }

    public function applyWeekdayPreset(string $preset): void
    {
        $this->form['weekday_mask'] = match ($preset) {
            'workdays' => PlannerRecurringTask::WEEKDAY_MASK_WORKDAYS,
            'weekend'  => PlannerRecurringTask::WEEKDAY_MASK_WEEKEND,
            'all'      => PlannerRecurringTask::WEEKDAY_MASK_ALL,
            default    => null,
        };
    }

    public function setMonthlyPattern(?string $pattern): void
    {
        $this->form['monthly_pattern'] = $pattern;
        // Defaults setzen damit das Preview nicht leer bleibt
        if ($pattern === 'day_of_month' && empty($this->form['monthly_day_of_month'])) {
            $this->form['monthly_day_of_month'] = 1;
        }
        if ($pattern === 'ordinal_weekday') {
            if ($this->form['monthly_ordinal'] === null) $this->form['monthly_ordinal'] = 1;
            if ($this->form['monthly_weekday'] === null) $this->form['monthly_weekday'] = 0;
        }
    }

    public function updatedRecurrenceEndDateInput($value)
    {
        $this->form['recurrence_end_date'] = $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function updatedNextDueDateInput($value)
    {
        $this->form['next_due_date'] = $value ? \Carbon\Carbon::parse($value) : null;
    }

    public function save()
    {
        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.story_points' => 'nullable|in:xs,s,m,l,xl,xxl',
            'form.priority' => 'required|in:low,normal,high',
            'form.planned_minutes' => 'nullable|integer|min:0',
            'form.user_in_charge_id' => 'nullable|integer',
            'form.project_slot_id' => 'nullable|integer|exists:planner_project_slots,id',
            'form.recurrence_type' => 'required|in:daily,weekly,monthly,yearly',
            'form.recurrence_interval' => 'required|integer|min:1',
            'form.recurrence_end_date' => 'nullable|date',
            'form.next_due_date' => 'nullable|date',
            'form.is_active' => 'boolean',
            'form.auto_delete_old_tasks' => 'boolean',
            'form.auto_mark_as_done' => 'boolean',

            'form.weekday_mask' => 'nullable|integer|min:0|max:127',
            'form.monthly_pattern' => 'nullable|in:day_of_month,ordinal_weekday',
            'form.monthly_day_of_month' => 'nullable|integer|between:-1,31',
            'form.monthly_ordinal' => 'nullable|integer|in:-1,1,2,3,4',
            'form.monthly_weekday' => 'nullable|integer|between:0,6',

            'form.lead_time_days' => 'nullable|integer|min:0|max:365',
            'form.chain_on_complete' => 'boolean',
            'form.max_occurrences' => 'nullable|integer|min:1',
            'form.skip_weekends' => 'boolean',
        ]);

        $user = Auth::user();

        $data = array_merge($this->form, [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'project_id' => $this->project->id,
        ]);

        if ($this->editingId) {
            $recurringTask = PlannerRecurringTask::findOrFail($this->editingId);
            $recurringTask->update($data);
            $this->dispatch('notifications:store', [
                'title' => 'Wiederkehrende Aufgabe aktualisiert',
                'message' => 'Die wiederkehrende Aufgabe wurde erfolgreich aktualisiert.',
                'notice_type' => 'success',
                'noticable_type' => get_class($recurringTask),
                'noticable_id' => $recurringTask->id,
            ]);
        } else {
            $recurringTask = PlannerRecurringTask::create($data);
            $this->dispatch('notifications:store', [
                'title' => 'Wiederkehrende Aufgabe erstellt',
                'message' => 'Die wiederkehrende Aufgabe wurde erfolgreich erstellt.',
                'notice_type' => 'success',
                'noticable_type' => get_class($recurringTask),
                'noticable_id' => $recurringTask->id,
            ]);
        }

        $this->loadRecurringTasks();
        $this->closeForm();
    }

    public function delete($id)
    {
        $recurringTask = PlannerRecurringTask::findOrFail($id);
        $noticableType = get_class($recurringTask);
        $noticableId = $recurringTask->id;
        $recurringTask->delete();
        
        $this->loadRecurringTasks();
        
        $this->dispatch('notifications:store', [
            'title' => 'Wiederkehrende Aufgabe gelöscht',
            'message' => 'Die wiederkehrende Aufgabe wurde erfolgreich gelöscht.',
            'notice_type' => 'success',
            'noticable_type' => $noticableType,
            'noticable_id' => $noticableId,
        ]);
    }

    public function toggleActive($id)
    {
        $recurringTask = PlannerRecurringTask::findOrFail($id);
        $recurringTask->is_active = !$recurringTask->is_active;
        $recurringTask->save();
        
        $this->loadRecurringTasks();
    }

    /**
     * Berechnet eine Live-Vorschau der nächsten 3 Termine basierend auf dem aktuellen Form-Stand,
     * ohne ein Model zu persistieren.
     */
    public function getPreviewOccurrencesProperty(): array
    {
        // Mindestens Datum + Typ + Intervall nötig für eine sinnvolle Vorschau
        if (empty($this->form['next_due_date'])) return [];

        $shadow = new PlannerRecurringTask();
        $shadow->recurrence_type = $this->form['recurrence_type'];
        $shadow->recurrence_interval = (int) ($this->form['recurrence_interval'] ?? 1);
        $shadow->next_due_date = $this->form['next_due_date'] instanceof \Carbon\Carbon
            ? $this->form['next_due_date']
            : \Carbon\Carbon::parse($this->form['next_due_date']);
        $shadow->recurrence_end_date = $this->form['recurrence_end_date']
            ? ($this->form['recurrence_end_date'] instanceof \Carbon\Carbon
                ? $this->form['recurrence_end_date']
                : \Carbon\Carbon::parse($this->form['recurrence_end_date']))
            : null;
        $shadow->weekday_mask = $this->form['weekday_mask'] ?? null;
        $shadow->monthly_pattern = $this->form['monthly_pattern'] ?? null;
        $shadow->monthly_day_of_month = $this->form['monthly_day_of_month'] ?? null;
        $shadow->monthly_ordinal = $this->form['monthly_ordinal'] ?? null;
        $shadow->monthly_weekday = $this->form['monthly_weekday'] ?? null;
        $shadow->skip_weekends = (bool) ($this->form['skip_weekends'] ?? false);
        $shadow->max_occurrences = $this->form['max_occurrences'] ?? null;
        $shadow->occurrences_count = 0;

        try {
            return $shadow->nextOccurrences(3);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function render()
    {
        $projectSlots = $this->project
            ? PlannerProjectSlot::where('project_id', $this->project->id)
                ->orderBy('order')
                ->get()
            : collect();

        $teamUsers = Auth::user()
            ->currentTeam
            ->users()
            ->orderBy('name')
            ->get();

        return view('planner::livewire.recurring-tasks-tab', [
            'projectSlots' => $projectSlots,
            'teamUsers' => $teamUsers,
            'recurrenceTypes' => collect([
                ['value' => 'daily', 'label' => 'Täglich'],
                ['value' => 'weekly', 'label' => 'Wöchentlich'],
                ['value' => 'monthly', 'label' => 'Monatlich'],
                ['value' => 'yearly', 'label' => 'Jährlich'],
            ]),
            'storyPointsOptions' => collect(TaskStoryPoints::cases())->map(fn($sp) => [
                'value' => $sp->value,
                'label' => $sp->label(),
            ]),
            'priorityOptions' => collect(TaskPriority::cases())->map(fn($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }
}

