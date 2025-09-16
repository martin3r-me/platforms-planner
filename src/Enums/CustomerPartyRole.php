<?php

namespace Platform\Planner\Enums;

enum CustomerPartyRole: string
{
    case PRIMARY_CONTACT = 'primary_contact';
    case CONTACT = 'contact';
    case PROJECT_MANAGER_INTERNAL = 'project_manager_internal';
    case PROJECT_MANAGER_EXTERNAL = 'project_manager_external';
    case STAKEHOLDER = 'stakeholder';
    case BILLING = 'billing';
}


