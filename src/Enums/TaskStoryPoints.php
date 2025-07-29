<?php

namespace Platform\Planner\Enums;

enum TaskStoryPoints: string
{
    case XS = 'xs';
    case S = 's';
    case M = 'm';
    case L = 'l';
    case XL = 'xl';
    case XXL = 'xxl';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function points(): int
	{
	    return match($this) {
	        self::XS => 1,
	        self::S => 2,
	        self::M => 3,
	        self::L => 5,
	        self::XL => 8,
	        self::XXL => 13,
	    };
	}
}