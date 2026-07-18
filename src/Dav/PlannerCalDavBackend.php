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
use Sabre\CalDAV\Backend\AbstractBackend;
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
class PlannerCalDavBackend extends AbstractBackend
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
            '{'.CalDavPlugin::NS_CALDAV.'}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO']),
        ];
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
        throw new Forbidden('Der Aufgaben-Kalender ist schreibgeschützt.');
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        // Schritt 4 (Write-Back) hängt hier ein: STATUS:COMPLETED -> lifecycle_state.
        throw new Forbidden('Der Aufgaben-Kalender ist schreibgeschützt.');
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        throw new Forbidden('Der Aufgaben-Kalender ist schreibgeschützt.');
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
