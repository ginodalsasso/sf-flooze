<?php

declare(strict_types=1);

namespace App\Enum;

enum RecurrenceUnitEnum: string
{
    case DAY = 'day';
    case MONTH = 'month';
}