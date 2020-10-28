<?php

declare(strict_types=1);

namespace App\Listener;

use App\Consume\Driver\DriverAbstract;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnFinish;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @Listener
 */
class OnFinishListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('worker');
    }

    public function listen(): array
    {
        return [
            OnFinish::class,
        ];
    }

    /**
     * @param OnFinish $event
     */
    public function process(object $event)
    {
        /** @var DriverAbstract */
        $driver = $this->container->get(DriverAbstract::class);
        $data = $driver->parseAckFinish($event->data);
        $driver->ack($data);
    }
}
