<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'attachments_disk' => env('ATTACHMENTS_DISK', 'attachments_local'),
    'avatars_disk' => env('AVATARS_DISK', env('ATTACHMENTS_DISK', 'attachments_local')),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'attachments_local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => true,
        ],

        'attachments_ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'root' => env('FTP_ROOT', ''),
            'url' => env('FTP_URL'),
            'passive' => true,
            'ssl' => false,
            'timeout' => 30,
            'ignorePassiveAddress' => true,
            'throw' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
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
            'report' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
