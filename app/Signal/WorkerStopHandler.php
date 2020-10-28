<?php

declare(strict_types=1);

namespace App\Signal;

use App\Consume\Driver\DriverAbstract;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Process\ProcessManager;
use Hyperf\Signal\SignalHandlerInterface;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;
use Swoole\Server;

class WorkerStopHandler implements SignalHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ConfigInterface
     */
    protected $config;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
    }

    public function listen(): array
    {
        return [
            [self::WORKER, SIGTERM],
            [self::WORKER, SIGINT],
        ];
    }

    public function handle(int $signal): void
    {
        ProcessManager::setRunning(false);

        /** @var Server */
        $server = $this->container->get(Server::class);

        if ($server->taskworker) {
            // task worker没有driver，只判断consumingCount即可
            // 循环判断等待消费完所有消息，如果没有消费完就超时被强制杀死，超时配置为server.setting.max_wait_time
            while ($server->consumingCount->get() > 0) {
                Coroutine::sleep(0.1);
            }
        } else {
            // event worker，停止driver继续获取数据
            /** @var DriverAbstract */
            $driver = $this->container->get(DriverAbstract::class);
            $driver->setRunning(false);

            // 循环判断等待消费完所有消息，如果没有消费完就超时被强制杀死，超时配置为server.setting.max_wait_time
            while ($server->consumingCount->get() > 0 || $driver->hasAck()) {
                Coroutine::sleep(0.1);
            }
        }

        $server->stop();
    }
}
