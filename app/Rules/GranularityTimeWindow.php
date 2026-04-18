<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GranularityTimeWindow implements ValidationRule
{
    protected int $start;
    protected int $end;
    protected string $granularity;

    /**
     * Create a new rule instance.
     *
     * @param int $start
     * @param int $end
     * @param string $granularity
     */
    public function __construct(int $start, int $end, string $granularity)
    {
        $this->start = $start;
        $this->end = $end;
        $this->granularity = $granularity;
    }


    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'The time window is too small for the specified granularity.';
    }

    /**
     * Validate the time window based on granularity.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        // Calculate the time window
        $timeWindow = $this->end - $this->start;

        // Check the granularity and validate the time window
        switch ($this->granularity) {
            case 'DAILY':
                if ($timeWindow < 86400) { // 86400 seconds in a day
                    $fail('The time window is too small for DAILY granularity.');
                }
                break;

            case 'WEEKLY':
                if ($timeWindow < 604800) { // 604800 seconds in a week
                    $fail('The time window is too small for WEEKLY granularity.');
                }
                break;

            case 'MONTHLY':
                if ($timeWindow < 2592000) { // 2592000 seconds in a month
                    $fail('The time window is too small for MONTHLY granularity.');
                }
                break;

            default:
                $fail('Invalid granularity value.');
                break;
        }
    }
}
