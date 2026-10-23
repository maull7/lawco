<?php

return [
    'daily_limit' => 3000,
    'maximum_limit' => 3000,
    'batch_size' => 500,
    'time' => '20:00',
    'timezone' => 'Asia/Jakarta',
    'queue' => 'parsing-auto',
    'connection' => 'redis',
    'extract_daily_limit' => 1000,
    'extract_maximum_limit' => 1000,
    'extract_time' => '22:00',
    'extract_queue' => 'ai-auto',
];
