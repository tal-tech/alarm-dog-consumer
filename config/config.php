<?php

declare(strict_types=1);

use Hyperf\Contract\StdoutLoggerInterface;

return [
    'app_name' => env('APP_NAME', 'skeleton'),
    'app_env' => env('APP_ENV', 'dev'),
    'start_processes' => env('START_PROCESSES', 'consumer'),
    'scan_cacheable' => env('SCAN_CACHEABLE', false),
    StdoutLoggerInterface::class => [
        'log_level' => explode(',', env('STDOUT_LOG_LEVEL', 'debug,info,notice,warning,error,critical,alert,emergency')),
    ],
];
