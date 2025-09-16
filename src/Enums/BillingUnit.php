<?php

namespace Platform\Planner\Enums;

enum BillingUnit: string
{
    case HOUR = 'hour';
    case DAY = 'day';
    case ITEM = 'item';
    case SERVICE = 'service';
}


