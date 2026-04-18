<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'oss'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
           // 'root' => storage_path('app'),
           'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path(''),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        "oss" => [
            "driver" => "oss",
            "access_key_id" => env("OSS_ACCESS_KEY_ID"),           // Required, yourAccessKeyId
            "access_key_secret" => env("OSS_ACCESS_KEY_SECRET"),       // Required, yourAccessKeySecret
            "bucket" => env("OSS_BUCKET"),                  // Required, for example: my-bucket
            "endpoint" => env("OSS_ENDPOINT"),                // Required, for example: oss-cn-shanghai.aliyuncs.com
            "internal" => env("OSS_INTERNAL", null),          // Optional, for example: oss-cn-shanghai-internal.aliyuncs.com
            "domain" => env("OSS_DOMAIN", null),            // Optional, for example: oss.my-domain.com
            "is_cname" => env("OSS_CNAME", false),            // Optional, if the Endpoint is a custom domain name, this must be true, see: https://github.com/aliyun/aliyun-oss-php-sdk/blob/572d0f8e099e8630ae7139ed3fdedb926c7a760f/src/OSS/OssClient.php#L113C1-L122C78
            "prefix" => env("OSS_PREFIX", ""),              // Optional, the prefix of the store path
            "use_ssl" => env("OSS_SSL", true),              // Optional, whether to use HTTPS
            "throw" => env("OSS_THROW", true),            // Optional, whether to throw an exception that causes an error
            "signatureVersion" => env("OSS_SIGNATURE_VERSION", "v1"), // Optional, select v1 or v4 as the signature version
            "region" => env("OSS_REGION", ""),              // Optional, for example: cn-shanghai, used only when v4 signature version is selected
            "options" => [],                                 // Optional, add global configuration parameters, For example: [\OSS\OssClient::OSS_CHECK_MD5 => false]
            "macros" => []                                  // Optional, add custom Macro, For example: [\App\Macros\ListBuckets::class, \App\Macros\CreateBucket::class]
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
