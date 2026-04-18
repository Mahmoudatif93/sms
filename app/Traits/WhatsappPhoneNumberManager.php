<?php

namespace App\Traits;

use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Log;

trait WhatsappPhoneNumberManager
{
    /**
     * Normalize a phone number by ensuring it starts with a '+' sign and converting it to E164 format.
     *
     * @param string $phoneNumber The phone number to normalize.
     * @return string The phone number in E164 format.
     * @throws InvalidArgumentException If the phone number format is invalid.
     */
    function normalizePhoneNumber(string $phoneNumber): string
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            // Ensure the phone number starts with a plus sign
            if (!str_starts_with($phoneNumber, '+')) {
                // If the phone number doesn't start with a plus sign, prepend it
                $phoneNumber = '+' . $phoneNumber;
            }
            $numberProto = $phoneUtil->parse($phoneNumber);
            return $phoneUtil->format($numberProto, PhoneNumberFormat::E164); // E164 format includes the '+' sign
        } catch (NumberParseException $e) {
           return $phoneNumber;
        }
    }

    public function getCountryCodeFromPhoneNumber(string $phoneNumber): ?string
    {
        // Assuming you use Google's libphonenumber library or similar for phone number validation
        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumberObj = $phoneUtil->parse($phoneNumber, null);
            return $phoneUtil->getRegionCodeForNumber($phoneNumberObj);
        } catch (\libphonenumber\NumberParseException $e) {
            Log::error("Failed to parse phone number: $phoneNumber");
            return null;
        }
    }
}
