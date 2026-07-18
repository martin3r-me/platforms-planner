<?php

namespace Platform\Planner\Dav;

use Platform\Core\Contracts\DavModuleInterface;
use Platform\Core\Dav\DavContext;
use Platform\Planner\Services\CalDav\TaskVTodoMapper;

/**
 * Stellt die Planner-Aufgaben als CalDAV-Kalender (VTODO) an der Core-DAV-
 * Infrastruktur bereit. Siehe docs/caldav.md.
 */
class PlannerCalDavModule implements DavModuleInterface
{
    public function key(): string
    {
        return 'planner';
    }

    public function type(): string
    {
        return 'caldav';
    }

    public function backend(DavContext $context): object
    {
        return new PlannerCalDavBackend($context, new TaskVTodoMapper());
    }
}
