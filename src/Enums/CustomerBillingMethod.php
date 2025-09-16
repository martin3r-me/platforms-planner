<?php

namespace Platform\Planner\Enums;

enum CustomerBillingMethod: string
{
    case TIME_AND_MATERIAL = 'time_and_material';
    case FIXED_PRICE = 'fixed_price';
    case RETAINER = 'retainer';
}


