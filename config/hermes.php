<?php

return [
    'base_url' => env('HERMES_BASE_URL', 'http://127.0.0.1:18643/v1'),
    'token' => env('HERMES_TOKEN') ?: env('API_SERVER_KEY'),
    'connect_timeout' => (int) env('HERMES_CONNECT_TIMEOUT', 10),
    'timeout' => (int) env('HERMES_TIMEOUT', 15),
    'run_timeout' => (int) env('HERMES_RUN_TIMEOUT', 120),
    'poll_seconds' => (int) env('HERMES_POLL_SECONDS', 2),
    'runs_endpoint' => env('HERMES_RUNS_ENDPOINT', '/runs'),
];
