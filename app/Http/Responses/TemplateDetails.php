<?php

namespace App\Http\Responses;

class TemplateDetails extends Template
{

    public array $components;

    public function __construct($template)
    {
        parent::__construct($template);
        $this->components = $this->processComponents($template);
    }


    protected function processComponents($template)
    {
        if ($template['category'] !== 'AUTHENTICATION') {
            return $template['components'];
        }

        // Iterate and modify components for AUTHENTICATION category
        foreach ($template['components'] as &$component) {
            if ($component['type'] === 'BUTTONS') {
                $this->addOtpDetailsToButtons($component['buttons']);
            }
        }

        return $template['components'];
    }

    private function addOtpDetailsToButtons(array &$buttons): void
    {
        foreach ($buttons as &$button) {
            if (!empty($button['url'])) {
                $queryParams = $this->parseUrlQueryParams($button['url']);

                // Set otp_type from URL if available, or default to an application-defined type
                $button['otp_type'] = $queryParams['otp_type'] ?? 'ZERO_TAP';

                // Extract supported apps from URL parameters
                $button['supported_apps'] = [
                    [
                        'package_name' => $queryParams['package_name'] ?? 'default.package',
                        'signature_hash' => $queryParams['signature_hash'] ?? 'default_hash',
                    ]
                ];

                $button['autofill_text'] = $queryParams['cta_display_name'] ?? null;

            }
        }
    }

    private function parseUrlQueryParams(string $url): array
    {
        $queryParams = [];
        $parsedUrl = parse_url($url);

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        return $queryParams;
    }
}
