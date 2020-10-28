<?php

declare(strict_types=1);

namespace App\Listener;

use App\Consume\Driver\DriverAbstract;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeWorkerStart;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Swoole\Server;

/**
 * @Listener
 */
class BeforeWorkerStartListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var mixed|StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('sql');
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
    }

    public function listen(): array
    {
        return [
            BeforeWorkerStart::class,
        ];
    }

    /**
     * @param BeforeWorkerStart $event
     */
    public function process(object $event)
    {
        /** @var Server */
        $server = $event->server;

        $this->stdoutLogger->alert($event->workerId . '#' . getmypid());
        $this->stdoutLogger->alert('start_processes:' . config('start_processes', ''));

        // 开始消费
        if (! $server->taskworker && strpos(config('start_processes', ''), 'consumer') !== false) {
            $this->startConsume($event->workerId);
        }
    }

    /**
     * 开始消费.
     *
     * @param int $workerId
     */
    protected function startConsume($workerId)
    {
        /** @var ConfigInterface */
        $config = $this->container->get(ConfigInterface::class);

        $consumerDriver = $config->get('consumer.driver');
        $this->stdoutLogger->alert('workerId:' . $workerId);
        $this->stdoutLogger->alert('mq:' . $consumerDriver);
        $driver = $config->get('consumer.drivers.' . $consumerDriver);
        /** @var DriverAbstract $entry */
        $entry = make(
            $driver['class'],
            [
                'config' => $driver['config'],
            ]
        );
        $this->container->set(DriverAbstract::class, $entry);

        $entry->consume();
    }
}
