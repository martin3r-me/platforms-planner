<?php

namespace Platform\Planner\Enums;

enum ProjectKind: string
{
    case RUN = 'run';
    case PROJECT = 'project';

    public function label(): string
    {
        return match ($this) {
            self::RUN => 'Run',
            self::PROJECT => 'Project',
        };
    }

    public function prefix(): string
    {
        return config('planner.kind_prefix.' . $this->value, strtoupper($this->value));
    }
}
