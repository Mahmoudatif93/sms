<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for WhatsApp integration
    | including webhook verification and mock settings.
    |
    */

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Mock Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the WhatsApp mock service for testing purposes.
    | When mock is enabled, no real messages will be sent to WhatsApp API.
    |
    */

    'mock' => [
        // Enable/disable mock mode
        'enabled' => env('WHATSAPP_MOCK_ENABLED', false),
        
        // Log all mock messages for debugging
        'log_messages' => env('WHATSAPP_MOCK_LOG_MESSAGES', true),
        
        // Simulate API delays (in milliseconds)
        'simulate_delays' => env('WHATSAPP_MOCK_SIMULATE_DELAYS', false),
        'delay_min' => env('WHATSAPP_MOCK_DELAY_MIN', 100),
        'delay_max' => env('WHATSAPP_MOCK_DELAY_MAX', 500),
        
        // Simulate random failures (0-100 percentage)
        'failure_rate' => env('WHATSAPP_MOCK_FAILURE_RATE', 0),
        
        // Default mock responses
        'responses' => [
            'success' => [
                'messaging_product' => 'whatsapp',
                'contacts' => [],
                'messages' => []
            ],
            'errors' => [
                'rate_limit' => [
                    'message' => 'Rate limit exceeded',
                    'code' => 80007,
                    'error_subcode' => 2494055
                ],
                'invalid_phone' => [
                    'message' => 'Invalid phone number',
                    'code' => 100,
                    'error_subcode' => 33
                ],
                'template_not_found' => [
                    'message' => 'Template not found',
                    'code' => 132000,
                    'error_subcode' => 2494002
                ]
            ]
        ]
    ]
];
