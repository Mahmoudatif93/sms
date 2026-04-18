<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for SMS processing and background jobs
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Background Processing Threshold
    |--------------------------------------------------------------------------
    |
    | The number of SMS recipients above which processing will be moved to
    | background jobs. This helps prevent timeouts for large SMS campaigns.
    |
    */
    'background_processing_threshold' => env('SMS_BACKGROUND_THRESHOLD', 10000),
    'background_varaiable_processing_threshold'=>env('SMS_BACKGROUND_VARAIABLE_THRESHOLD', 5000),

    /*
    |--------------------------------------------------------------------------
    | Background Sending Threshold
    |--------------------------------------------------------------------------
    |
    | The number of SMS recipients above which sending will be moved to
    | background jobs after approval. This helps prevent timeouts for large campaigns.
    |
    */
    'background_sending_threshold' => env('SMS_BACKGROUND_SENDING_THRESHOLD', 5000),

    /*
    |--------------------------------------------------------------------------
    | Batch Processing Size
    |--------------------------------------------------------------------------
    |
    | The number of SMS recipients to process in each batch during background
    | processing. Smaller batches use less memory but take longer.
    |
    */
    'batch_processing_size' => env('SMS_BATCH_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Processing Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds for background SMS processing jobs.
    |
    */
    'processing_timeout' => env('SMS_PROCESSING_TIMEOUT', 3600), // 1 hour

    /*
    |--------------------------------------------------------------------------
    | Auto Approval Threshold
    |--------------------------------------------------------------------------
    |
    | SMS campaigns below this threshold will be auto-approved after processing.
    | Larger campaigns will require manual approval.
    |
    */
    'auto_approval_threshold' => env('SMS_AUTO_APPROVAL_THRESHOLD', 1000),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for SMS processing jobs
    |
    */
    'queue' => [
        'statistics' => env('SMS_STATISTICS_QUEUE', 'statistics'),
        'sending' => env('SMS_SENDING_QUEUE', 'SendSms'),
        'preparation' => env('SMS_PREPARATION_QUEUE', 'PrepareMessage'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Settings for notifying users about processing completion
    |
    */
    'notifications' => [
        'enabled' => env('SMS_NOTIFICATIONS_ENABLED', true),
        'email' => env('SMS_EMAIL_NOTIFICATIONS', true),
        'in_app' => env('SMS_IN_APP_NOTIFICATIONS', true),
        'sms' => env('SMS_SMS_NOTIFICATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Notification Messages
    |--------------------------------------------------------------------------
    |
    | Templates for SMS notifications sent to users
    |
    */
    'notification_messages' => [
        'success' => 'تم إكمال معالجة إحصائيات الرسائل النصية بنجاح! عدد الأرقام: {count} رقم. التكلفة: {cost} ريال. يرجى مراجعة النتائج والموافقة عليها.',
        'failure' => 'تنبيه: فشلت معالجة إحصائيات الرسائل النصية. معرف المعالجة: {processing_id}. السبب: {error}',
        'sending_success' => 'تم بدء إرسال حملة الرسائل النصية بنجاح! عدد الأرقام: {count} رقم. معرف المعالجة: {processing_id}',
        'sending_failure' => 'تنبيه: فشل في بدء إرسال حملة الرسائل النصية. معرف المعالجة: {processing_id}. السبب: {error}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excel Processing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for processing Excel files containing phone numbers
    |
    */
    'excel' => [
        'max_file_size' => env('SMS_EXCEL_MAX_SIZE', 10240), // KB
        'chunk_size' => env('SMS_EXCEL_CHUNK_SIZE', 50000), // rows per chunk
        'supported_formats' => ['xlsx', 'xls'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting settings for SMS processing to prevent system overload
    |
    */
    'rate_limiting' => [
        'enabled' => env('SMS_RATE_LIMITING_ENABLED', true),
        'max_concurrent_jobs' => env('SMS_MAX_CONCURRENT_JOBS', 5),
        'delay_between_batches' => env('SMS_BATCH_DELAY', 100), // milliseconds
    ],
];
