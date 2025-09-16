<?php

namespace Platform\Planner\Enums;

enum ProjectType: string
{
    case INTERNAL = 'internal';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return $this === self::CUSTOMER ? 'Kunde' : 'Intern';
    }
}


