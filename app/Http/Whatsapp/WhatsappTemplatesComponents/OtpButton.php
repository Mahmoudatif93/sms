<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class OtpButton
{

    private string $otpType;
    private ?string $text;
    private ?string $autofillText;
    private ?bool $zeroTapTermsAccepted;
    private array $supportedApps;

    public function __construct(
        string  $otpType,
        ?string $text = null,
        ?string $autofillText = null,
        ?bool   $zeroTapTermsAccepted = null,
        array   $supportedApps = []
    )
    {
        if (!in_array($otpType, ['copy_code', 'one_tap', 'zero_tap'])) {
            throw new InvalidArgumentException('Invalid OTP type.');
        }

        $this->otpType = $otpType;
        $this->text = $text;
        $this->autofillText = $autofillText;
        $this->zeroTapTermsAccepted = $zeroTapTermsAccepted;
        $this->supportedApps = $supportedApps;

        // Validate supported apps if provided
        foreach ($supportedApps as $app) {
            if (!isset($app['package_name']) || !isset($app['signature_hash'])) {
                throw new InvalidArgumentException('Each supported app must have a package_name and signature_hash.');
            }
        }
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getAutofillText(): ?string
    {
        return $this->autofillText;
    }

    public function getZeroTapTermsAccepted(): ?bool
    {
        return $this->zeroTapTermsAccepted;
    }

    public function getSupportedApps(): array
    {
        return $this->supportedApps;
    }

    public function getOtpType(): string
    {
        return $this->otpType;
    }

    public function toArray(): array
    {
        $buttonArray = [
            'type' => 'OTP',
            'otp_type' => $this->otpType,
        ];

        if ($this->text !== null) {
            $buttonArray['text'] = $this->text;
        }

        if ($this->autofillText !== null) {
            $buttonArray['autofill_text'] = $this->autofillText;
        }

        if ($this->otpType === 'zero_tap' && $this->zeroTapTermsAccepted !== null) {
            $buttonArray['zero_tap_terms_accepted'] = $this->zeroTapTermsAccepted;
        }

        if (!empty($this->supportedApps)) {
            $buttonArray['supported_apps'] = $this->supportedApps;
        }

        return $buttonArray;
    }

    public function getType(): string
    {
        return 'buttons'; // Since this is an OTP button, return 'OTP' as the type
    }

}
