<?php

use Illuminate\Support\Str;

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web'],

    'waits' => [
        'redis:default' => 60,
        'redis:ai' => 60,
        'redis:jdih' => 300,
        'redis:parsing' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 512,

    'defaults' => [
        'default' => [
            'connection' => 'redis',
            'queue' => ['default', 'high'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],

        'parsing' => [
            'connection' => 'redis',
            'queue' => ['parsing'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 512,
            'tries' => 1,
            'timeout' => 700,
            'nice' => 10,
        ],

        'ai' => [
            'connection' => 'redis',
            'queue' => ['ai'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 200,
            'nice' => 5,
        ],

        'jdih' => [
            'connection' => 'redis',
            'queue' => ['jdih'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 1200,
            'nice' => 5,
        ],
    ],

    'environments' => [
        'production' => [
            'default' => [
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'parsing' => [
                'maxProcesses' => 2,
            ],
            'ai' => [
                'maxProcesses' => 3,
            ],
            'jdih' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'default' => [
                'maxProcesses' => 2,
            ],
            'parsing' => [
                'maxProcesses' => 1,
            ],
            'ai' => [
                'maxProcesses' => 2,
            ],
            'jdih' => [
                'maxProcesses' => 1,
            ],
        ],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
