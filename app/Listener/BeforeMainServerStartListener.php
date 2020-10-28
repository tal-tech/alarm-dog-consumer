<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Swoole\Atomic;

/**
 * @Listener
 */
class BeforeMainServerStartListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function listen(): array
    {
        return [
            BeforeMainServerStart::class,
        ];
    }

    /**
     * @param BeforeMainServerStart $event
     */
    public function process(object $event)
    {
        $event->server->consumingCount = new Atomic(0);
    }
}
