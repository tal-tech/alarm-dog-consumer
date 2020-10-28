<?php

declare(strict_types=1);

return [
    'default' => [
        'handler' => [
            'class' => Monolog\Handler\StreamHandler::class,
            'constructor' => [
                'stream' => env('LOG_PATH', BASE_PATH . '/runtime/logs') . '/hyperf.log',
                'level' => Monolog\Logger::toMonologLevel(env('LOG_LEVEL_DEFAULT', 'DEBUG')),
            ],
        ],
        'formatter' => [
            'class' => env('LOG_FORMATTER_CLASS', Monolog\Formatter\LineFormatter::class),
            'constructor' => [
                'format' => null,
                'dateFormat' => 'Y-m-d H:i:s',
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'pipeline' => [
        'handler' => [
            'class' => \Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => env('LOG_PATH', BASE_PATH . '/runtime/logs') . '/alarm-pipeline.log',
                'level' => Monolog\Logger::NOTICE,
                'maxFiles' => 5,
                'bubble' => true,
                'filePermission' => null,
                'useLocking' => false,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\JsonFormatter::class,
            'constructor' => [
                'format' => null,
                'dateFormat' => null,
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
    'noticer' => [
        'handler' => [
            'class' => \Monolog\Handler\RotatingFileHandler::class,
            'constructor' => [
                'filename' => env('LOG_PATH', BASE_PATH . '/runtime/logs') . '/alarm-noticer.log',
                'level' => Monolog\Logger::ERROR,
                'maxFiles' => 5,
                'bubble' => true,
                'filePermission' => null,
                'useLocking' => false,
            ],
        ],
        'formatter' => [
            'class' => Monolog\Formatter\LineFormatter::class,
            'constructor' => [
                'format' => null,
                'dateFormat' => null,
                'allowInlineLineBreaks' => true,
            ],
        ],
    ],
];
