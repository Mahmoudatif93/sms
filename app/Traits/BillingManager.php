<?php

namespace App\Traits;

use Carbon\Carbon;
use InvalidArgumentException;

trait BillingManager
{
    /**
     * Compute a billing window [start, end] for a given frequency.
     *
     * @param string      $frequency  e.g. 'monthly' or 'yearly'
     * @param Carbon|null $anchor     optional anchor date (defaults to now())
     * @return array{start: Carbon, end: Carbon}
     */
    function computeBillingWindow(string $frequency, ?Carbon $anchor = null): array
    {
        $start = ($anchor ?? now())->copy()->startOfDay();

        $nextStart = match (strtolower($frequency)) {
            'monthly' => $start->copy()->addMonthsNoOverflow(1),
            'yearly', 'annual', 'annually' => $start->copy()->addYear(),
            default => throw new InvalidArgumentException("Unsupported frequency: {$frequency}"),
        };

        $end = $nextStart->copy()->subSecond(); // 23:59:59 of the day before the nextStart

        return ['start' => $start, 'end' => $end];
    }
}
