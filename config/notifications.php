<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | This option controls the default notification channels for different
    | types of notifications. You can specify which channels should be
    | used for each notification type.
    |
    */

    'default_channels' => [
        'review' => ['telegram'],
        'admin' => ['telegram'],
        'alert' => ['telegram'],
        'error' => ['telegram'],
        'warning' => ['telegram'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    |
    | Here you can configure specific settings for each notification channel.
    | These settings will be merged with the main service configurations.
    |
    */

    'channels' => [
        'telegram' => [
            'enabled' => env('TELEGRAM_NOTIFICATIONS_ENABLED', true),
            'retry_attempts' => env('TELEGRAM_RETRY_ATTEMPTS', 3),
            'retry_delay' => env('TELEGRAM_RETRY_DELAY', 5), // seconds
            'max_message_length' => env('TELEGRAM_MAX_MESSAGE_LENGTH', 4096),
            'default_parse_mode' => env('TELEGRAM_DEFAULT_PARSE_MODE', 'HTML'),
            'rate_limit' => [
                'enabled' => env('TELEGRAM_RATE_LIMIT_ENABLED', true),
                'max_requests' => env('TELEGRAM_RATE_LIMIT_MAX_REQUESTS', 30),
                'per_seconds' => env('TELEGRAM_RATE_LIMIT_PER_SECONDS', 60),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    |
    | Define message templates for different types of notifications.
    | You can use placeholders that will be replaced with actual values.
    |
    */

    'templates' => [
        'review' => [
            'title' => '🔍 <b>رسالة تحتاج مراجعة</b>',
            'format' => "{title}\n\n📝 <b>المحتوى:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
        'admin' => [
            'title' => '👨‍💼 <b>إشعار إداري</b>',
            'format' => "{title}\n\n📝 <b>المحتوى:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
        'alert' => [
            'title' => '🚨 <b>تنبيه مهم</b>',
            'format' => "{title}\n\n⚠️ <b>التنبيه:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
        'error' => [
            'title' => '❌ <b>خطأ في النظام</b>',
            'format' => "{title}\n\n🐛 <b>الخطأ:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
        'warning' => [
            'title' => '⚠️ <b>تحذير</b>',
            'format' => "{title}\n\n📢 <b>التحذير:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
        'parent_review' => [
            'title' => '👨‍👩‍👧‍👦 <b>رسالة من حساب فرعي تحتاج مراجعة</b>',
            'format' => "{title}\n\n📝 <b>المحتوى:</b>\n{message}\n\n{metadata}⏰ <b>الوقت:</b> {timestamp}",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Notification Channels
    |--------------------------------------------------------------------------
    |
    | List of all available notification channels in the system.
    |
    */
    'available_channels' => [
        'sms' => [
            'name' => 'SMS',
            'description' => 'Send notifications via SMS',
            'enabled' => true,
            'config' => [
                'max_length' => 160,
                'rate_limit' => 100, // per minute
                'supports_delivery_confirmation' => true,
            ],
        ],
        'email' => [
            'name' => 'Email',
            'description' => 'Send notifications via Email',
            'enabled' => true,
            'config' => [
                'max_length' => 10000,
                'rate_limit' => 500, // per minute
                'supports_delivery_confirmation' => false,
            ],
        ],
        'telegram' => [
            'name' => 'Telegram',
            'description' => 'Send notifications via Telegram',
            'enabled' => env('TELEGRAM_BOT_TOKEN') ? true : false,
            'bot_token' => env('TELEGRAM_BOT_TOKEN', '8168275784:AAHkKgN-XtBHPtOYFua5t5AZxigtUweBRNU'),
            'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org/bot'),
            'timeout' => env('TELEGRAM_TIMEOUT', 10),
            'config' => [
                'max_length' => 4096,
                'rate_limit' => 30, // per minute
                'supports_delivery_confirmation' => false,
                'retry_attempts' => env('TELEGRAM_RETRY_ATTEMPTS', 3),
                'retry_delay' => env('TELEGRAM_RETRY_DELAY', 5), // seconds
                'default_parse_mode' => env('TELEGRAM_DEFAULT_PARSE_MODE', 'HTML'),
            ],
            'channels' => [
                'review' => env('TELEGRAM_REVIEW_CHANNEL_ID', '-1002398836696'),
                'admin' => env('TELEGRAM_ADMIN_CHANNEL_ID', '-1002949014927'),
                'alerts' => env('TELEGRAM_ALERTS_CHANNEL_ID', '-1002949014927'),
            ],
        ],
        'push' => [
            'name' => 'Push Notification',
            'description' => 'Send push notifications to mobile devices',
            'enabled' => false,
            'config' => [
                'max_length' => 256,
                'rate_limit' => 1000, // per minute
                'supports_delivery_confirmation' => true,
            ],
        ],
        'database' => [
            'name' => 'Database',
            'description' => 'Store notifications in database',
            'enabled' => true,
            'config' => [
                'max_length' => 65535,
                'rate_limit' => 10000, // per minute
                'supports_delivery_confirmation' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Types
    |--------------------------------------------------------------------------
    |
    | Define different types of notifications and their default settings.
    |
    */
    'available_types' => [
        'login' => [
            'name' => 'تسجيل الدخول',
            'description' => 'إشعارات تسجيل الدخول',
            'default_channels' => ['sms'],
            'priority' => 'high',
        ],
        'review' => [
            'name' => 'مراجعة',
            'description' => 'إشعارات المراجعة',
            'default_channels' => ['email', 'database'],
            'priority' => 'normal',
        ],
        'admin' => [
            'name' => 'إداري',
            'description' => 'إشعارات إدارية',
            'default_channels' => ['email', 'telegram'],
            'priority' => 'high',
        ],
        'alert' => [
            'name' => 'تنبيه',
            'description' => 'تنبيهات النظام',
            'default_channels' => ['sms', 'email', 'telegram'],
            'priority' => 'urgent',
        ],
        'reminder' => [
            'name' => 'تذكير',
            'description' => 'تذكيرات',
            'default_channels' => ['email', 'database'],
            'priority' => 'low',
        ],
        'statistics' => [
            'name' => 'إحصائيات',
            'description' => 'إشعارات الإحصائيات',
            'default_channels' => ['email', 'telegram'],
            'priority' => 'normal',
        ],
        'approval' => [
            'name' => 'موافقة',
            'description' => 'إشعارات الموافقة',
            'default_channels' => ['sms', 'email'],
            'priority' => 'high',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification queue processing.
    |
    */
    'queue' => [
        'enabled' => env('NOTIFICATION_QUEUE_ENABLED', false),
        'queue_name' => env('NOTIFICATION_QUEUE_NAME', 'notifications'),
        'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'redis'),
        'retry_after' => 300, // 5 minutes
        'max_tries' => 3,
        'queue_threshold' => 10, // Queue if more than 10 recipients
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for failed notification retries.
    |
    */
    'retry' => [
        'max_retries' => 3,
        'retry_delays' => [60, 300, 900], // 1 min, 5 min, 15 min
        'exponential_backoff' => true,
        'retry_job_enabled' => true,
        'retry_job_schedule' => '*/5 * * * *', // Every 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Global rate limiting configuration.
    |
    */
    'rate_limiting' => [
        'enabled' => true,
        'global_limit_per_minute' => 1000,
        'per_user_limit_per_minute' => 10,
        'per_channel_limits' => [
            'sms' => 100,
            'email' => 500,
            'telegram' => 30,
            'push' => 1000,
            'database' => 10000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Preferences
    |--------------------------------------------------------------------------
    |
    | Default notification preferences for different types.
    |
    */
    'default_preferences' => [
        'login' => [
            'sms' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
            'email' => [
                'enabled' => false,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
        ],
        'review' => [
            'email' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => true,
            ],
            'database' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
        ],
        'admin' => [
            'email' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
            'telegram' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
        ],
        'alert' => [
            'sms' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
            'email' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
            'telegram' => [
                'enabled' => true,
                'frequency' => 'immediate',
                'quiet_hours' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification templates.
    |
    */
    'template_config' => [
        'cache_enabled' => true,
        'cache_ttl' => 3600, // 1 hour
        'default_locale' => 'ar',
        'supported_locales' => ['ar', 'en'],
        'template_path' => resource_path('views/notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification analytics and reporting.
    |
    */
    'analytics' => [
        'enabled' => true,
        'cache_ttl' => 1800, // 30 minutes
        'retention_days' => 90, // Keep logs for 90 days
        'real_time_updates' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification system monitoring.
    |
    */
    'monitoring' => [
        'health_check_enabled' => true,
        'health_check_interval' => 300, // 5 minutes
        'alert_on_failure_rate' => 10, // Alert if failure rate > 10%
        'alert_on_queue_size' => 1000, // Alert if queue size > 1000
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how notification attempts should be logged.
    |
    */

    'logging' => [
        'enabled' => env('NOTIFICATION_LOGGING_ENABLED', true),
        'log_successful' => env('NOTIFICATION_LOG_SUCCESSFUL', true),
        'log_failed' => env('NOTIFICATION_LOG_FAILED', true),
        'log_channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
        'include_message_content' => env('NOTIFICATION_LOG_INCLUDE_CONTENT', false),
    ],



    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Configure fallback behavior when primary channels fail.
    |
    */

    'fallback' => [
        'enabled' => env('NOTIFICATION_FALLBACK_ENABLED', true),
        'channels' => [
            'telegram' => ['log'], // If telegram fails, log the message
        ],
        'max_fallback_attempts' => env('NOTIFICATION_MAX_FALLBACK_ATTEMPTS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for different notification types.
    |
    */

    'validation' => [
        'message' => [
            'required' => true,
            'min_length' => 1,
            'max_length' => 4096,
        ],
        'channel' => [
            'required' => false,
            'allowed_values' => ['review', 'admin', 'alerts', 'error', 'warning'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific notification features.
    |
    */

    'features' => [
        'message_formatting' => env('NOTIFICATION_MESSAGE_FORMATTING', true),
        'emoji_support' => env('NOTIFICATION_EMOJI_SUPPORT', true),
        'url_preview' => env('NOTIFICATION_URL_PREVIEW', false),
        'silent_notifications' => env('NOTIFICATION_SILENT_MODE', false),
        'message_threading' => env('NOTIFICATION_MESSAGE_THREADING', false),
    ],

];
