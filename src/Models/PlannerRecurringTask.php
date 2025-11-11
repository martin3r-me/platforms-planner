<?php

namespace Platform\Planner\Models;

use Platform\Planner\Enums\TaskPriority;
use Platform\Planner\Enums\TaskStoryPoints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;

/**
 * @ai.description Wiederkehrende Aufgaben werden automatisch in regelmäßigen Abständen als Tasks erstellt. Sie können einem Projekt, ProjectSlot, Sprint oder persönlich zugeordnet sein.
 */
class PlannerRecurringTask extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_in_charge_id',
        'team_id',
        'title',
        'description',
        'story_points',
        'priority',
        'planned_minutes',
        'project_id',
        'project_slot_id',
        'sprint_id',
        'task_group_id',
        'recurrence_type', // daily, weekly, monthly, yearly
        'recurrence_interval', // z.B. 1 = jede Woche, 2 = alle 2 Wochen
        'recurrence_end_date', // optional, wann die Wiederholung endet
        'next_due_date', // wann die nächste Aufgabe erstellt werden soll
        'is_active',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'story_points' => TaskStoryPoints::class,
        'recurrence_end_date' => 'datetime',
        'next_due_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());

            $model->uuid = $uuid;

            if (! $model->user_id) {
                $model->user_id = Auth::id();
            }

            if (! $model->team_id) {
                $model->team_id = Auth::user()->currentTeam->id;
            }

            // Standard-Werte setzen
            if (! $model->recurrence_interval) {
                $model->recurrence_interval = 1;
            }
            if ($model->is_active === null) {
                $model->is_active = true;
            }
        });
    }

    public function setUserInChargeIdAttribute($value)
    {
        $this->attributes['user_in_charge_id'] = empty($value) || $value === 'null' ? null : (int)$value;
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function project()
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function projectSlot()
    {
        return $this->belongsTo(PlannerProjectSlot::class, 'project_slot_id');
    }

    public function sprint()
    {
        return $this->belongsTo(PlannerSprint::class, 'sprint_id');
    }

    public function taskGroup()
    {
        return $this->belongsTo(PlannerTaskGroup::class, 'task_group_id');
    }

    public function userInCharge()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id');
    }

    /**
     * Erstellt eine Task basierend auf dieser wiederkehrenden Aufgabe
     */
    public function createTask(): PlannerTask
    {
        $task = PlannerTask::create([
            'user_id' => $this->user_id,
            'user_in_charge_id' => $this->user_in_charge_id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'story_points' => $this->story_points,
            'priority' => $this->priority,
            'planned_minutes' => $this->planned_minutes,
            'project_id' => $this->project_id,
            'project_slot_id' => $this->project_slot_id,
            'task_group_id' => $this->task_group_id,
            'due_date' => $this->next_due_date,
        ]);

        // Nächsten Termin berechnen
        $this->calculateNextDueDate();
        $this->save();

        return $task;
    }

    /**
     * Berechnet das nächste Fälligkeitsdatum basierend auf dem Wiederholungsmuster
     */
    public function calculateNextDueDate(): void
    {
        if (!$this->next_due_date) {
            $this->next_due_date = now();
        }

        $current = $this->next_due_date;

        $this->next_due_date = match($this->recurrence_type) {
            'daily' => $current->copy()->addDays($this->recurrence_interval),
            'weekly' => $current->copy()->addWeeks($this->recurrence_interval),
            'monthly' => $current->copy()->addMonths($this->recurrence_interval),
            'yearly' => $current->copy()->addYears($this->recurrence_interval),
            default => $current->copy()->addDay(),
        };
    }

    /**
     * Prüft, ob eine neue Task erstellt werden sollte
     */
    public function shouldCreateTask(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->recurrence_end_date && now()->isAfter($this->recurrence_end_date)) {
            return false;
        }

        if (!$this->next_due_date) {
            return false;
        }

        // Erstelle Task, wenn das nächste Fälligkeitsdatum erreicht oder überschritten wurde
        return now()->isSameDay($this->next_due_date) || now()->isAfter($this->next_due_date);
    }
}

