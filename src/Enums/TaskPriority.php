<?php

namespace Platform\Planner\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Normal => 'Normal',
            self::High => 'Hoch',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => '⬇',
            self::Normal => '⭘',
            self::High => '⬆',
        };
    }
}