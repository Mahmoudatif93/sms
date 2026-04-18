<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;


/**
 * Custom validation rule to ensure timestamps are set to midnight UTC.
 */
class MidnightUtc implements ValidationRule
{

    /**
     * Get the validation error message.
     *
     * @return string  The validation error message.
     */
    public function message(): string
    {
        return 'The :attribute must be a valid timestamp set to midnight UTC.';
    }


    /**
     * Check if the given timestamp is set to midnight UTC.
     *
     * @param int $timestamp The timestamp to check.
     * @return bool  True if the timestamp is at midnight UTC, false otherwise.
     */
    protected function isMidnightUtc($timestamp): bool
    {
        $date = \DateTime::createFromFormat('U', $timestamp);
        if ($date === false) {
            return false;
        }

        // Check if the time is at midnight UTC
        return $date->format('H:i:s') == '21:00:00' && $date->getTimezone()->getName() === '+00:00';
    }


    /**
     * Validate the attribute value.
     *
     * @param string $attribute The attribute name.
     * @param mixed $value The value of the attribute.
     * @param Closure $fail The callback to execute if validation fails.
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->isMidnightUtc($value)) {
            $fail($this->message());
        }
    }
}
