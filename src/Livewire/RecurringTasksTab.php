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
        ];
        $this->recurrenceEndDateInput = '';
        $this->nextDueDateInput = '';
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
            ]);
        } else {
            PlannerRecurringTask::create($data);
            $this->dispatch('notifications:store', [
                'title' => 'Wiederkehrende Aufgabe erstellt',
                'message' => 'Die wiederkehrende Aufgabe wurde erfolgreich erstellt.',
                'notice_type' => 'success',
            ]);
        }

        $this->loadRecurringTasks();
        $this->closeForm();
    }

    public function delete($id)
    {
        $recurringTask = PlannerRecurringTask::findOrFail($id);
        $recurringTask->delete();
        
        $this->loadRecurringTasks();
        
        $this->dispatch('notifications:store', [
            'title' => 'Wiederkehrende Aufgabe gelöscht',
            'message' => 'Die wiederkehrende Aufgabe wurde erfolgreich gelöscht.',
            'notice_type' => 'success',
        ]);
    }

    public function toggleActive($id)
    {
        $recurringTask = PlannerRecurringTask::findOrFail($id);
        $recurringTask->is_active = !$recurringTask->is_active;
        $recurringTask->save();
        
        $this->loadRecurringTasks();
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
            'recurrenceTypes' => [
                ['value' => 'daily', 'label' => 'Täglich'],
                ['value' => 'weekly', 'label' => 'Wöchentlich'],
                ['value' => 'monthly', 'label' => 'Monatlich'],
                ['value' => 'yearly', 'label' => 'Jährlich'],
            ],
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

