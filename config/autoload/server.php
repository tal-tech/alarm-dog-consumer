<?php

declare(strict_types=1);

use Hyperf\Server\Server;
use Hyperf\Server\SwooleEvent;

$consumer = require __DIR__ . '/consumer.php';

return [
    'mode' => SWOOLE_PROCESS,
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => env('SERVER_HTTP_HOST', '0.0.0.0'),
            'port' => (int) env('SERVER_HTTP_PORT', '9511'),
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                SwooleEvent::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
        ],
    ],
    'settings' => [
        'enable_coroutine' => true,
        'worker_num' => (int) env('SERVER_WORKER_NUM', count($consumer['drivers'])),
        'task_worker_num' => (int) env('SERVER_TASK_WORKER_NUM', swoole_cpu_num()),
        'task_enable_coroutine' => true,
        'pid_file' => BASE_PATH . '/runtime/hyperf.pid',
        'open_tcp_nodelay' => true,
        'max_coroutine' => 100000,
        'open_http2_protocol' => true,
        'max_request' => 100000,
        'socket_buffer_size' => 2 * 1024 * 1024,
        'buffer_output_size' => 2 * 1024 * 1024,
        // 可用于控制进程收到信号后最长多久不被杀死
        'max_wait_time' => (int) env('SERVER_MAX_WAIT_TIME', 5),
    ],
    'callbacks' => [
        SwooleEvent::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        SwooleEvent::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        SwooleEvent::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
        SwooleEvent::ON_TASK => [Hyperf\Framework\Bootstrap\TaskCallback::class, 'onTask'],
        SwooleEvent::ON_FINISH => [Hyperf\Framework\Bootstrap\FinishCallback::class, 'OnFinish'],
    ],
];
