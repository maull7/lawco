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

    'default' => env('FILESYSTEM_DISK', 'local'),

    // Disk for uploaded documents. Set PDF_DISK=r2 to use Cloudflare R2.
    'pdf_disk' => env('PDF_DISK', 'public'),

    // R2 disk used for uploaded documents (target of documents:migrate-to-r2).
    'r2_document_disk' => 'r2_documents',

    // Max data copied to R2 per day by documents:migrate-to-r2, in GB.
    'r2_daily_copy_limit_gb' => (float) env('R2_DAILY_COPY_LIMIT_GB', 5),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
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

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET', 'putusan-pdf'),
            'endpoint' => env('R2_ENDPOINT'),
            'url' => env('R2_PUBLIC_URL'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Separate from 'r2' (putusan MA). Own bucket; keys/endpoint fall back to the putusan R2 account.
        'r2_documents' => [
            'driver' => 's3',
            'key' => env('R2_DOCUMENTS_ACCESS_KEY_ID', env('R2_ACCESS_KEY_ID')),
            'secret' => env('R2_DOCUMENTS_SECRET_ACCESS_KEY', env('R2_SECRET_ACCESS_KEY')),
            'region' => 'auto',
            'bucket' => env('R2_DOCUMENTS_BUCKET'),
            'endpoint' => env('R2_DOCUMENTS_ENDPOINT', env('R2_ENDPOINT')),
            'url' => env('R2_DOCUMENTS_PUBLIC_URL'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
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
