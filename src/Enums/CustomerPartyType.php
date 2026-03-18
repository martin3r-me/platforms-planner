<?php

namespace Platform\Planner\Enums;

/**
 * @deprecated PlannerCustomerProjectParty ist deprecated. Entity-Verknüpfungen über OrganizationEntityLink.
 */
enum CustomerPartyType: string
{
    case INTERNAL = 'internal';
    case EXTERNAL = 'external';
}


