<?php

namespace Platform\Planner\Dav;

use Illuminate\Database\Eloquent\Builder;
use Platform\Core\Dav\DavContext;
use Platform\Core\Models\DavSubscription;
use Platform\Planner\Enums\TaskLifecycleState;
use Platform\Planner\Models\PlannerProject;
use Platform\Planner\Models\PlannerProjectUser;
use Platform\Planner\Models\PlannerTask;
use Platform\Planner\Services\CalDav\TaskVTodoMapper;
use Platform\Planner\Services\LifecycleService;
use Sabre\VObject\Component\VTodo;
use Sabre\VObject\Reader;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

/**
 * Read-only CalDAV-Backend für Planner-Aufgaben (VTODO).
 *
 * Ein Account (Core-{@see DavSubscription}, module=planner/type=caldav) zeigt
 * mehrere Listen: „Meine Aufgaben" (immer) plus je opt-in-Projekt eine Liste
 * (PlannerProjectUser.expose_in_caldav). Alle Schreib-Ops werfen {@see Forbidden}.
 *
 * Siehe docs/caldav.md.
 */
class PlannerCalDavBackend extends AbstractBackend implements SyncSupport
{
    private const MINE = 'mine';

    public function __construct(
        private readonly DavContext $context,
        private readonly TaskVTodoMapper $mapper,
    ) {
    }

    private function sub(): DavSubscription
    {
        return $this->context->subscription();
    }

    private function userId(): int
    {
        return (int) $this->sub()->user_id;
    }

    // ----------------------------------------------------------------
    // Kalender
    // ----------------------------------------------------------------

    public function getCalendarsForUser($principalUri)
    {
        if ($this->sub()->type !== 'caldav'
            || $principalUri !== 'principals/'.$this->userId()) {
            return [];
        }

        $calendars = [$this->calendarArray(self::MINE, 'meine-aufgaben', 'Meine Aufgaben')];

        foreach ($this->optedInProjects() as $project) {
            $calendars[] = $this->calendarArray(
                (int) $project->id,
                'projekt-'.$project->id,
                $project->name ?? ('Projekt '.$project->id),
            );
        }

        return $calendars;
    }

    /**
     * @param  string|int  $id
     * @return array<string, mixed>
     */
    private function calendarArray($id, string $uri, string $displayName): array
    {
        return [
            'id' => $id,
            'uri' => $uri,
            'principaluri' => 'principals/'.$this->userId(),
            '{DAV:}displayname' => $displayName,
            '{'.CalDavPlugin::NS_CALENDARSERVER.'}getctag' => $this->computeCtag($id),
            '{http://sabredav.org/ns}sync-token' => $this->computeCtag($id),
            '{'.CalDavPlugin::NS_CALDAV.'}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
        ];
    }

    /**
     * WebDAV-Sync (nötig für Apple Erinnerungen). Ohne Change-Log: bei jeder
     * Änderung Voll-Resync (Token veraltet -> null), sonst Delta leer.
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null)
    {
        $this->assertAllowedCalendar($calendarId);

        $current = $this->computeCtag($calendarId);

        if (! empty($syncToken) && $syncToken === $current) {
            return ['syncToken' => $current, 'added' => [], 'modified' => [], 'deleted' => []];
        }

        if (! empty($syncToken)) {
            // Token veraltet -> Client macht Voll-Resync (initial).
            return null;
        }

        // Initial-Sync: alle aktuellen Aufgaben als "added".
        $uris = $this->tasksQuery($calendarId)
            ->pluck('uuid')
            ->map(fn ($uuid) => $uuid.'.ics')
            ->all();

        return ['syncToken' => $current, 'added' => $uris, 'modified' => [], 'deleted' => []];
    }

    public function updateCalendar($calendarId, PropPatch $propPatch)
    {
        // Read-only.
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new Forbidden('Der Aufgaben-Kalender ist schreibgeschützt.');
    }

    public function deleteCalendar($calendarId)
    {
        throw new Forbidden('Der Aufgaben-Kalender ist schreibgeschützt.');
    }

    // ----------------------------------------------------------------
    // Objekte (VTODO)
    // ----------------------------------------------------------------

    public function getCalendarObjects($calendarId)
    {
        $this->assertAllowedCalendar($calendarId);

        return $this->tasksQuery($calendarId)
            ->get(['id', 'uuid', 'updated_at'])
            ->map(fn (PlannerTask $task) => [
                'id' => $task->id,
                'uri' => $task->uuid.'.ics',
                // calendarid ist Pflicht: AbstractBackend::calendarQuery reicht es
                // an getCalendarObject weiter (sonst "Undefined array key calendarid").
                'calendarid' => $calendarId,
                'etag' => TaskVTodoMapper::etagFor($task),
                'lastmodified' => $task->updated_at?->getTimestamp() ?? 0,
                'component' => 'vtodo',
            ])
            ->all();
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        $this->assertAllowedCalendar($calendarId);

        $task = $this->tasksQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if (! $task) {
            return null;
        }

        $data = $this->mapper->serialize($task);

        return [
            'id' => $task->id,
            'uri' => $objectUri,
            'calendarid' => $calendarId,
            'etag' => TaskVTodoMapper::etagFor($task),
            'lastmodified' => $task->updated_at?->getTimestamp() ?? 0,
            'size' => strlen($data),
            'calendardata' => $data,
            'component' => 'vtodo',
        ];
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $this->assertAllowedCalendar($calendarId);

        $vtodo = $this->readVtodo($calendarData);

        $task = new PlannerTask();
        $task->uuid = $this->uuidFromUri($objectUri);
        $task->user_id = $this->userId();
        $task->team_id = $this->sub()->team_id;
        $task->title = $this->summary($vtodo) ?: 'Aufgabe';
        $task->due_date = $this->due($vtodo);
        $task->lifecycle_state = TaskLifecycleState::ACTIVE;
        if ($calendarId !== self::MINE) {
            $task->project_id = (int) $calendarId;
        }
        $task->save();

        if ($this->isCompleted($vtodo)) {
            app(LifecycleService::class)->completeTask($task);
        }

        return TaskVTodoMapper::etagFor($task->refresh());
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        $this->assertAllowedCalendar($calendarId);

        $task = $this->tasksQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if (! $task) {
            throw new NotFound('Aufgabe nicht gefunden.');
        }

        $vtodo = $this->readVtodo($calendarData);

        // Titel NICHT zurückschreiben: die SUMMARY enthält den Projekt-Präfix
        // („Projekt · Aufgabe"), das würde den echten Titel verhunzen. Nur
        // Fälligkeit + Status syncen.
        if (($due = $this->due($vtodo)) !== null) {
            $task->due_date = $due;
        }
        $task->save();

        // Abhaken / Wieder-Öffnen über die Business-Logik.
        $lifecycle = app(LifecycleService::class);
        $completed = $this->isCompleted($vtodo);
        if ($completed && $task->lifecycle_state !== TaskLifecycleState::COMPLETED) {
            $lifecycle->completeTask($task);
        } elseif (! $completed && $task->lifecycle_state === TaskLifecycleState::COMPLETED) {
            $lifecycle->reopenTask($task);
        }

        return TaskVTodoMapper::etagFor($task->refresh());
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        $this->assertAllowedCalendar($calendarId);

        $task = $this->tasksQuery($calendarId)
            ->where('uuid', $this->uuidFromUri($objectUri))
            ->first();

        if ($task) {
            $task->delete();
        }
    }

    // ----------------------------------------------------------------
    // VTODO-Parsing (Write-Back)
    // ----------------------------------------------------------------

    private function readVtodo(string $calendarData): ?VTodo
    {
        $vcal = Reader::read($calendarData);

        return $vcal->VTODO ?? null;
    }

    private function summary(?VTodo $vtodo): string
    {
        return $vtodo && $vtodo->SUMMARY ? trim((string) $vtodo->SUMMARY) : '';
    }

    private function due(?VTodo $vtodo): ?\DateTimeInterface
    {
        if ($vtodo && $vtodo->DUE) {
            return $vtodo->DUE->getDateTime();
        }

        return null;
    }

    private function isCompleted(?VTodo $vtodo): bool
    {
        if (! $vtodo) {
            return false;
        }

        if ($vtodo->STATUS && strtoupper(trim((string) $vtodo->STATUS)) === 'COMPLETED') {
            return true;
        }

        return $vtodo->{'PERCENT-COMPLETE'} && (int) (string) $vtodo->{'PERCENT-COMPLETE'} >= 100;
    }

    // ----------------------------------------------------------------
    // Scoping / Sichtbarkeit
    // ----------------------------------------------------------------

    /**
     * Projekte, die der User fürs CalDAV freigeschaltet hat (default aus).
     *
     * @return \Illuminate\Support\Collection<int, PlannerProject>
     */
    private function optedInProjects()
    {
        $ids = PlannerProjectUser::query()
            ->where('user_id', $this->userId())
            ->where('expose_in_caldav', true)
            ->pluck('project_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        return PlannerProject::query()->whereIn('id', $ids)->get();
    }

    /**
     * Tasks eines Kalenders: „mine" = eigene Aufgaben, sonst die des Projekts.
     * Verworfene Aufgaben werden ausgeblendet.
     *
     * @param  string|int  $calendarId
     */
    private function tasksQuery($calendarId): Builder
    {
        $query = PlannerTask::query()
            ->where('lifecycle_state', '!=', TaskLifecycleState::DISCARDED->value);

        if ($calendarId === self::MINE) {
            return $query->where('user_id', $this->userId());
        }

        return $query->where('project_id', (int) $calendarId);
    }

    /**
     * @param  string|int  $calendarId
     */
    private function assertAllowedCalendar($calendarId): void
    {
        if ($calendarId === self::MINE) {
            return;
        }

        $allowed = PlannerProjectUser::query()
            ->where('user_id', $this->userId())
            ->where('expose_in_caldav', true)
            ->where('project_id', (int) $calendarId)
            ->exists();

        if (! $allowed) {
            throw new NotFound('Kalender nicht gefunden.');
        }
    }

    /**
     * CTag ändert sich bei Task-Edit (max updated_at) und bei Mitglieder-/
     * Zuordnungs-Änderung (count).
     *
     * @param  string|int  $calendarId
     */
    private function computeCtag($calendarId): string
    {
        $agg = $this->tasksQuery($calendarId)
            ->selectRaw('COUNT(*) as cnt, MAX(updated_at) as maxu')
            ->first();

        $max = $agg?->maxu ? strtotime((string) $agg->maxu) : 0;

        return $max.'-'.($agg?->cnt ?? 0);
    }

    private function uuidFromUri(string $objectUri): string
    {
        return preg_replace('/\.ics$/i', '', $objectUri);
    }
}
