<?php

declare(strict_types=1);

namespace App\Listener;

use App\Consume\Cache\CacheInterface;
use App\Consume\Driver\DriverAbstract;
use App\Support\ProcessMessage;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnPipeMessage;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @Listener
 */
class OnPipeMessageListener implements ListenerInterface
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
            OnPipeMessage::class,
        ];
    }

    public function process(object $event)
    {
        [$cmd, $params] = ProcessMessage::unpack($event->data);
        switch ($cmd) {
            case 'dataSync':
                return $this->handleDataSync($params);
            case 'dataSyncReady':
                return $this->handleDataSyncReady($params);
            default:
                # code...
                break;
        }
    }

    /**
     * 数据同步.
     *
     * @param array $params
     */
    protected function handleDataSync($params)
    {
        /** @var CacheInterface */
        $cache = $this->container->get(CacheInterface::class);
        $cache->cover($params);
    }

    /**
     * 数据同步就绪.
     *
     * @param array $params
     */
    protected function handleDataSyncReady($params)
    {
        /** @var DriverAbstract */
        $dataSource = $this->container->get(DriverAbstract::class);
        $dataSource->setReady();
    }
}
