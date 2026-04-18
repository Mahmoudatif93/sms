<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

class WhatsappValidPhoneNumber implements ValidationRule
{
    protected ?string $errorMessage = null;

    public function passes($attribute, $value): bool
    {
        return $this->validatePhoneNumber($value);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->validatePhoneNumber($value)) {
            $fail($this->errorMessage ?? 'The :attribute is invalid.');
        }
    }

    protected function validatePhoneNumber(string $value): bool
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            // Parse the phone number
            $number = $phoneNumberUtil->parse($value);

            // Validate the phone number
            if (!$phoneNumberUtil->isValidNumber($number)) {
                $this->errorMessage = 'Invalid phone number format.';
                return false;
            }

            $regionCode = $phoneNumberUtil->getRegionCodeForNumber($number);

            // Check if the number is valid for the specified region
            if (!$phoneNumberUtil->isValidNumberForRegion($number, $regionCode)) {
                $this->errorMessage = "Phone number is not valid for the region '{$regionCode}'.";
                return false;
            }

            // Validate if the number is of mobile type
            $numberType = $phoneNumberUtil->getNumberType($number);
            if ($numberType !== PhoneNumberType::MOBILE && $numberType !== PhoneNumberType::FIXED_LINE_OR_MOBILE) {
                $this->errorMessage = 'The phone number must be a mobile number.';
                return false;
            }

            return true;
        } catch (NumberParseException $e) {
            $this->errorMessage = "Invalid phone number format: {$e->getMessage()}";
            return false;
        } catch (\Exception $e) {
            $this->errorMessage = 'An unexpected error occurred.';
            return false;
        }
    }

    public function message(): string
    {
        return $this->errorMessage ?? 'The :attribute is invalid.';
    }
}
