<?php

namespace Platform\Planner\Enums;

enum ProjectType: string
{
    case INTERNAL = 'internal';
    case CUSTOMER = 'customer';
    case EVENT = 'event';

    public function label(): string
    {
        return match($this) {
            self::CUSTOMER => 'Kunde',
            self::EVENT => 'Event',
            default => 'Intern'
        };
    }
}


