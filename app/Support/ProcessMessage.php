<?php

declare(strict_types=1);

namespace App\Support;

use Hyperf\Utils\ApplicationContext;
use Swoole\Server;

/**
 * 进程间通讯，协议约定.
 */
class ProcessMessage
{
    /**
     * pack.
     *
     * @param string $cmd
     * @param array $params
     * @return string
     */
    public static function pack($cmd, $params)
    {
        $packet = [
            'cmd' => $cmd,
            'params' => $params,
        ];

        return json_encode($packet, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * unpack.
     *
     * @param string $packet
     * @return array [$cmd, $params]
     */
    public static function unpack($packet)
    {
        $data = json_decode($packet, true);

        return [$data['cmd'] ?? null, $data['params'] ?? []];
    }

    /**
     * 广播到所有event+task wokers进程.
     *
     * @param string $cmd 命令
     * @param array $params 参数
     * @param bool $ignoreCurrent 是否忽略当前进程
     */
    public static function broadcastToAllWorkers(string $cmd, array $params = [], bool $ignoreCurrent = true)
    {
        /** @var Server */
        $server = ApplicationContext::getContainer()->get(Server::class);

        $message = static::pack($cmd, $params);

        $workerNum = $server->setting['worker_num'] + $server->setting['task_worker_num'];
        for ($workerId = 0; $workerId < $workerNum; $workerId++) {
            if (! $ignoreCurrent || $workerId != $server->worker_id) {
                $server->sendMessage($message, $workerId);
            }
        }
    }

    /**
     * 广播到所有task wokers进程.
     *
     * @param string $cmd 命令
     * @param array $params 参数
     */
    public static function broadcastToTaskWorkers(string $cmd, array $params = [])
    {
        /** @var Server */
        $server = ApplicationContext::getContainer()->get(Server::class);

        $message = static::pack($cmd, $params);

        $workerNum = $server->setting['worker_num'] + $server->setting['task_worker_num'];
        for ($workerId = $server->setting['worker_num']; $workerId < $workerNum; $workerId++) {
            $server->sendMessage($message, $workerId);
        }
    }

    /**
     * 广播到所有event wokers进程.
     *
     * @param string $cmd 命令
     * @param array $params 参数
     * @param bool $ignoreCurrent 是否忽略当前进程
     */
    public static function broadcastToEventWorkers(string $cmd, array $params = [], bool $ignoreCurrent = true)
    {
        /** @var Server */
        $server = ApplicationContext::getContainer()->get(Server::class);

        $message = static::pack($cmd, $params);

        for ($workerId = 0; $workerId < $server->setting['worker_num']; $workerId++) {
            if (! $ignoreCurrent || $workerId != $server->worker_id) {
                $server->sendMessage($message, $workerId);
            }
        }
    }
}
