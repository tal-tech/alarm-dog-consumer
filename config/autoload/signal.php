<?php

declare(strict_types=1);

return [
    'handlers' => [
        // Hyperf\Signal\Handler\WorkerStopHandler::class => PHP_INT_MIN
        \App\Signal\WorkerStopHandler::class,
    ],
    'timeout' => 5.0,
];
