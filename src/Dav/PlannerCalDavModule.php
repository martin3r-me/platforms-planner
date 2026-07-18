<?php

namespace Platform\Planner\Dav;

use Platform\Core\Contracts\DavModuleInterface;
use Platform\Core\Dav\DavContext;
use Platform\Core\Dav\PrincipalBackend;
use Platform\Planner\Services\CalDav\TaskVTodoMapper;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\DAV\ICollection;

/**
 * Registriert die Planner-Aufgaben als CalDAV-Kalender (VTODO) an der Core-DAV-
 * Infrastruktur. Siehe docs/caldav.md.
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

    public function rootNode(DavContext $context, PrincipalBackend $principals): ICollection
    {
        return new CalendarRoot(
            $principals,
            new PlannerCalDavBackend($context, new TaskVTodoMapper()),
        );
    }

    public function plugins(): array
    {
        return [new CalDavPlugin()];
    }
}
