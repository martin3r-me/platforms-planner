<?php

namespace Platform\Planner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;
use Platform\Planner\Enums\LogbookEntryType;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Logbuch-Eintrag eines Projekts: Entscheidung, Meilenstein oder Risiko mit Begruendung. Append-only — Korrekturen sind neue Eintraege mit supersedes_entry_id. Verweist auf Aufgaben/Commits/Canvas, statt sie zu wiederholen.
 */
class PlannerProjectLogbookEntry extends Model
{
    protected $table = 'planner_project_logbook_entries';

    public const REF_TYPES = ['task', 'commit', 'canvas', 'canvas_snapshot', 'url'];

    public const SOURCES = ['manual', 'draft', 'agent'];

    protected $fillable = [
        'uuid',
        'project_id',
        'team_id',
        'type',
        'title',
        'rationale',
        'occurred_on',
        'refs',
        'supersedes_entry_id',
        'source',
        'created_by_user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'type' => LogbookEntryType::class,
        'occurred_on' => 'date',
        'refs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });

        // Append-only: ein Logbuch, das man umschreiben kann, ist keins.
        // Geloescht wird nur per DB-Cascade mit dem Projekt.
        static::updating(function () {
            throw new LogicException('Logbuch-Eintraege sind unveraenderlich. Korrektur = neuer Eintrag mit supersedes_entry_id.');
        });
        static::deleting(function () {
            throw new LogicException('Logbuch-Eintraege koennen nicht geloescht werden.');
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(PlannerProject::class, 'project_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_entry_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_entry_id');
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('occurred_on')->orderBy('id');
    }

    /** Nur Eintraege, die nicht durch einen spaeteren ersetzt wurden. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNotExists(function ($q) {
            $q->selectRaw('1')
                ->from('planner_project_logbook_entries as newer')
                ->whereColumn('newer.supersedes_entry_id', 'planner_project_logbook_entries.id');
        });
    }

    public function toToolArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'rationale' => $this->rationale,
            'occurred_on' => $this->occurred_on?->toDateString(),
            'refs' => $this->refs ?? [],
            'supersedes_entry_id' => $this->supersedes_entry_id,
            'source' => $this->source,
            'created_by' => $this->createdBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
