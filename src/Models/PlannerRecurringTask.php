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
use Platform\Organization\Services\StorePlannedTime;
use Carbon\Carbon;

/**
 * @ai.description Wiederkehrende Aufgaben werden automatisch in regelmäßigen Abständen als Tasks erstellt.
 *
 * Unterstützte Muster (recurrence_type):
 *   - daily  : Alle N Tage. Optional weekday_mask (Bitmaske) für „nur an diesen Wochentagen".
 *   - weekly : Alle N Wochen. Optional weekday_mask für mehrere Wochentage pro Woche.
 *   - monthly: Alle N Monate. Zwei monatlichen Patterns möglich:
 *              * day_of_month: an einem festen Tag (1..31 oder -1 = letzter Tag)
 *              * ordinal_weekday: am N-ten Wochentag (z. B. 1. Montag, letzter Freitag)
 *   - yearly : Alle N Jahre, gleicher Tag/Monat wie next_due_date.
 *
 * Weitere Felder:
 *   - lead_time_days   : Wie viele Tage vor next_due_date soll die Aufgabe schon angelegt werden? (Default 0)
 *   - chain_on_complete: Wenn aktiv, wird beim Erledigen/Löschen der letzten Instanz sofort die nächste erzeugt.
 *   - max_occurrences  : Optionales Limit auf die Anzahl angelegter Instanzen.
 *   - skip_weekends    : Berechnete Termine auf Sa/So werden auf den nächsten Montag verschoben.
 */
class PlannerRecurringTask extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    // Wochentag-Bits (ISO: Mo=0..So=6, gespeichert als 2^n)
    public const WEEKDAY_BITS = [
        0 => 1,   // Mo
        1 => 2,   // Di
        2 => 4,   // Mi
        3 => 8,   // Do
        4 => 16,  // Fr
        5 => 32,  // Sa
        6 => 64,  // So
    ];
    public const WEEKDAY_MASK_WORKDAYS = 1 + 2 + 4 + 8 + 16;   // Mo-Fr
    public const WEEKDAY_MASK_WEEKEND  = 32 + 64;              // Sa-So
    public const WEEKDAY_MASK_ALL      = 127;                   // Mo-So

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
        'recurrence_type',
        'recurrence_interval',
        'recurrence_end_date',
        'next_due_date',
        'is_active',
        'auto_delete_old_tasks',
        'auto_mark_as_done',

        // Neue Felder (Step A):
        'weekday_mask',
        'monthly_pattern',
        'monthly_day_of_month',
        'monthly_ordinal',
        'monthly_weekday',
        'lead_time_days',
        'chain_on_complete',
        'max_occurrences',
        'occurrences_count',
        'skip_weekends',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'story_points' => TaskStoryPoints::class,
        'recurrence_end_date' => 'datetime',
        'next_due_date' => 'datetime',
        'is_active' => 'boolean',
        'auto_delete_old_tasks' => 'boolean',
        'auto_mark_as_done' => 'boolean',

        'weekday_mask' => 'integer',
        'monthly_day_of_month' => 'integer',
        'monthly_ordinal' => 'integer',
        'monthly_weekday' => 'integer',
        'lead_time_days' => 'integer',
        'chain_on_complete' => 'boolean',
        'max_occurrences' => 'integer',
        'occurrences_count' => 'integer',
        'skip_weekends' => 'boolean',
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

            if (! $model->recurrence_interval) {
                $model->recurrence_interval = 1;
            }
            if ($model->is_active === null) {
                $model->is_active = true;
            }
            if ($model->auto_delete_old_tasks === null) {
                $model->auto_delete_old_tasks = false;
            }
            if ($model->auto_mark_as_done === null) {
                $model->auto_mark_as_done = false;
            }
            if ($model->chain_on_complete === null) {
                $model->chain_on_complete = false;
            }
            if ($model->skip_weekends === null) {
                $model->skip_weekends = false;
            }
            if ($model->lead_time_days === null) {
                $model->lead_time_days = 0;
            }
            if ($model->occurrences_count === null) {
                $model->occurrences_count = 0;
            }
        });
    }

    public function setUserInChargeIdAttribute($value)
    {
        $this->attributes['user_in_charge_id'] = empty($value) || $value === 'null' ? null : (int)$value;
    }

    // ─── Relations ────────────────────────────────────────────────────

    public function user()        { return $this->belongsTo(\Platform\Core\Models\User::class); }
    public function team()        { return $this->belongsTo(\Platform\Core\Models\Team::class); }
    public function project()     { return $this->belongsTo(PlannerProject::class, 'project_id'); }
    public function projectSlot() { return $this->belongsTo(PlannerProjectSlot::class, 'project_slot_id'); }
    public function sprint()      { return $this->belongsTo(PlannerSprint::class, 'sprint_id'); }
    public function taskGroup()   { return $this->belongsTo(PlannerTaskGroup::class, 'task_group_id'); }
    public function userInCharge(){ return $this->belongsTo(\Platform\Core\Models\User::class, 'user_in_charge_id'); }
    public function tasks()       { return $this->hasMany(PlannerTask::class, 'recurring_task_id'); }

    // ─── Task-Erstellung ─────────────────────────────────────────────

    /**
     * Erstellt eine neue Task aus dieser Vorlage und rollt next_due_date weiter.
     *
     * - auto_delete_old_tasks: vorhandene Instanzen werden vor dem Anlegen entfernt
     * - auto_mark_as_done: neue Task ist sofort erledigt
     * - occurrences_count wird inkrementiert
     * - next_due_date wird so weitergerollt, dass es in der Zukunft liegt (kein Backlog)
     */
    public function createTask(): PlannerTask
    {
        if ($this->auto_delete_old_tasks) {
            $this->tasks()->delete();
        }

        $task = PlannerTask::create([
            'user_id' => $this->user_id,
            'user_in_charge_id' => $this->user_in_charge_id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'story_points' => $this->story_points,
            'priority' => $this->priority,
            'project_id' => $this->project_id,
            'project_slot_id' => $this->project_slot_id,
            'task_group_id' => $this->task_group_id,
            'recurring_task_id' => $this->id,
            'due_date' => $this->next_due_date,
            'is_done' => $this->auto_mark_as_done,
        ]);

        if ($this->planned_minutes && (int) $this->planned_minutes > 0) {
            app(StorePlannedTime::class)->store([
                'team_id' => $this->team_id,
                'user_id' => $this->user_id,
                'context_type' => PlannerTask::class,
                'context_id' => $task->id,
                'planned_minutes' => (int) $this->planned_minutes,
                'note' => null,
                'is_active' => true,
            ]);
        }

        if ($this->auto_mark_as_done) {
            $task->done_at = now();
            $task->save();
        }

        // Zähler hochsetzen und next_due_date weiter rollen,
        // bis es in der Zukunft liegt (Cron-Robustheit: keine Backlog-Stapel)
        $this->occurrences_count = (int) $this->occurrences_count + 1;
        $this->rollNextDueDateForward();
        $this->save();

        return $task;
    }

    // ─── shouldCreateTask + Preview ──────────────────────────────────

    /**
     * Soll der Cron jetzt eine neue Task aus dieser Vorlage anlegen?
     * Berücksichtigt is_active, recurrence_end_date, max_occurrences und lead_time_days.
     */
    public function shouldCreateTask(): bool
    {
        if (! $this->is_active) return false;
        if (! $this->next_due_date) return false;

        if ($this->recurrence_end_date && now()->isAfter($this->recurrence_end_date)) {
            return false;
        }
        if ($this->max_occurrences !== null && $this->occurrences_count >= $this->max_occurrences) {
            return false;
        }

        // Erstellungs-Schwelle: lead_time_days VOR dem next_due_date
        $threshold = $this->next_due_date->copy()->subDays((int) $this->lead_time_days);
        return now()->greaterThanOrEqualTo($threshold);
    }

    /**
     * Liefert die nächsten N voraussichtlichen Fälligkeiten (für UI-Vorschau).
     * Ändert das Model NICHT — arbeitet auf einer geklonten Carbon-Instanz.
     */
    public function nextOccurrences(int $n = 3): array
    {
        if (! $this->next_due_date || ! $this->recurrence_type || ! $this->recurrence_interval) {
            return [];
        }

        $result = [];
        $cursor = $this->next_due_date->copy();
        $countLimit = $this->max_occurrences ? max(0, $this->max_occurrences - $this->occurrences_count) : null;

        for ($i = 0; $i < $n; $i++) {
            if ($countLimit !== null && $i >= $countLimit) break;
            if ($this->recurrence_end_date && $cursor->greaterThan($this->recurrence_end_date)) break;

            $result[] = $cursor->copy();
            $cursor = $this->advance($cursor);
        }

        return $result;
    }

    // ─── Date-Berechnungen ───────────────────────────────────────────

    /**
     * Rollt next_due_date so weit nach vorn, dass es ≥ heute liegt
     * (verhindert dass der Cron 10× hintereinander Tasks für die Vergangenheit erstellt).
     * Mindestens ein Vorwärtsschritt.
     */
    protected function rollNextDueDateForward(): void
    {
        $cursor = $this->next_due_date?->copy() ?? now();
        $cursor = $this->advance($cursor);

        // Wenn das frische Datum schon in der Vergangenheit liegt: weiter rollen, bis in Zukunft.
        $safeGuard = 0;
        while ($cursor->lessThan(now()->startOfDay()) && $safeGuard < 365) {
            $cursor = $this->advance($cursor);
            $safeGuard++;
        }

        $this->next_due_date = $cursor;
    }

    /**
     * Ein Schritt vorwärts nach dem konfigurierten Muster.
     * Anschließend skip_weekends-Adjustierung.
     */
    protected function advance(Carbon $from): Carbon
    {
        $next = match ($this->recurrence_type) {
            'daily'   => $this->advanceDaily($from),
            'weekly'  => $this->advanceWeekly($from),
            'monthly' => $this->advanceMonthly($from),
            'yearly'  => $from->copy()->addYears(max(1, (int) $this->recurrence_interval)),
            default   => $from->copy()->addDay(),
        };

        return $this->adjustForWeekends($next);
    }

    protected function advanceDaily(Carbon $from): Carbon
    {
        $step = max(1, (int) $this->recurrence_interval);
        $next = $from->copy()->addDays($step);

        // Falls Wochentag-Maske gesetzt, auf nächsten erlaubten Wochentag rollen
        if ($this->weekday_mask) {
            $next = $this->rollToAllowedWeekday($next);
        }
        return $next;
    }

    protected function advanceWeekly(Carbon $from): Carbon
    {
        $intervalWeeks = max(1, (int) $this->recurrence_interval);

        if ($this->weekday_mask) {
            // Innerhalb der aktuellen Woche den nächsten erlaubten Wochentag suchen
            $candidate = $from->copy()->addDay();
            $maxLookahead = 7;
            for ($i = 0; $i < $maxLookahead; $i++) {
                if ($this->isWeekdayAllowed($candidate)) {
                    return $candidate;
                }
                $candidate = $candidate->addDay();
            }
            // Sollte nicht passieren wenn Maske > 0
            return $from->copy()->addWeeks($intervalWeeks);
        }

        return $from->copy()->addWeeks($intervalWeeks);
    }

    protected function advanceMonthly(Carbon $from): Carbon
    {
        $intervalMonths = max(1, (int) $this->recurrence_interval);
        $baseMonth = $from->copy()->addMonths($intervalMonths);

        return match ($this->monthly_pattern) {
            'day_of_month' => $this->monthlyByDayOfMonth($baseMonth),
            'ordinal_weekday' => $this->monthlyByOrdinalWeekday($baseMonth),
            default => $baseMonth, // Backward-compat: gleicher Tag-im-Monat
        };
    }

    protected function monthlyByDayOfMonth(Carbon $month): Carbon
    {
        $day = (int) $this->monthly_day_of_month;
        if ($day === 0) $day = 1;

        if ($day === -1) {
            return $month->copy()->endOfMonth()->startOfDay()
                ->setTime($month->hour, $month->minute);
        }

        $lastDay = (int) $month->copy()->endOfMonth()->day;
        $effectiveDay = min($day, $lastDay); // clamp 31 → letzter Tag des kürzeren Monats

        return $month->copy()->setDay($effectiveDay)->setTime($month->hour, $month->minute);
    }

    /**
     * Z. B. 1. Montag, letzter Freitag im Monat
     * monthly_ordinal: 1..4 oder -1 (letzter)
     * monthly_weekday: 0..6 (Mo..So)
     */
    protected function monthlyByOrdinalWeekday(Carbon $month): Carbon
    {
        $ordinal = (int) ($this->monthly_ordinal ?? 1);
        $weekday = (int) ($this->monthly_weekday ?? 0); // 0=Mo, 6=So

        // Start am 1. des Monats
        $candidate = $month->copy()->startOfMonth()->setTime($month->hour, $month->minute);

        if ($ordinal === -1) {
            // Vom Ende rückwärts suchen
            $last = $candidate->copy()->endOfMonth()->setTime($month->hour, $month->minute);
            for ($i = 0; $i < 7; $i++) {
                if ($this->carbonWeekdayToIso($last) === $weekday) {
                    return $last;
                }
                $last = $last->subDay();
            }
            return $candidate;
        }

        // Erstes Vorkommen des gewünschten Wochentags finden, dann (ordinal-1) Wochen addieren
        $firstMatch = $candidate->copy();
        for ($i = 0; $i < 7; $i++) {
            if ($this->carbonWeekdayToIso($firstMatch) === $weekday) break;
            $firstMatch = $firstMatch->addDay();
        }
        $target = $firstMatch->addWeeks($ordinal - 1);

        // Wenn das Ergebnis in den Folgemonat fällt, auf den letzten passenden Wochentag im Zielmonat zurückfallen
        if ($target->month !== $month->month) {
            return $this->monthlyByOrdinalWeekday(
                $month->copy()->subMonth() // Letztes Vorkommen im aktuellen Monat
            );
        }

        return $target;
    }

    protected function adjustForWeekends(Carbon $date): Carbon
    {
        if (! $this->skip_weekends) return $date;
        if ($date->isWeekday()) return $date;
        // Sa → Mo, So → Mo
        return $date->copy()->next(\Carbon\CarbonInterface::MONDAY)->setTime($date->hour, $date->minute);
    }

    protected function rollToAllowedWeekday(Carbon $date): Carbon
    {
        for ($i = 0; $i < 7; $i++) {
            if ($this->isWeekdayAllowed($date)) return $date;
            $date = $date->copy()->addDay();
        }
        return $date;
    }

    protected function isWeekdayAllowed(Carbon $date): bool
    {
        if (! $this->weekday_mask) return true;
        $iso = $this->carbonWeekdayToIso($date);
        $bit = self::WEEKDAY_BITS[$iso] ?? 0;
        return ($this->weekday_mask & $bit) !== 0;
    }

    /** Carbon::dayOfWeek (0=So..6=Sa) → unser ISO-Schema (0=Mo..6=So) */
    protected function carbonWeekdayToIso(Carbon $date): int
    {
        $sunday0 = $date->dayOfWeek; // 0=So, 1=Mo, ... 6=Sa
        return ($sunday0 + 6) % 7;   // 0=Mo..6=So
    }

    /**
     * Public-Variante von advance() — für Tests und Preview, ändert das Model nicht.
     */
    public function calculateNextDueDate(): void
    {
        if (! $this->next_due_date) {
            $this->next_due_date = now();
        }
        $this->rollNextDueDateForward();
    }
}
