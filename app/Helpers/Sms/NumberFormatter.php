<?php

namespace App\Helpers\Sms;

class NumberFormatter
{
    /**
     * Format a phone number to the required format.
     *
     * @param string $number
     * @return string
     */
    public static function formatNumber(string $number): string
    {
        $number = str_replace('+', '', $number);
        if (substr($number, 0, 2) == "05" && strlen($number) == 10) {
            $number = "966" . substr($number, 1);
        } elseif (substr($number, 0, 1) == "5" && strlen($number) == 9) {
            $number = "966" . $number;
        }
        return $number;
    }
}
