<?php

namespace Platform\Planner\Enums;

enum ProjectStatus: string
{
    case AKTIV = 'aktiv';
    case PASSIV = 'passiv';
    case INAKTIV = 'inaktiv';

    public function label(): string
    {
        return match ($this) {
            self::AKTIV => 'Aktiv',
            self::PASSIV => 'Passiv',
            self::INAKTIV => 'Inaktiv',
        };
    }
}
