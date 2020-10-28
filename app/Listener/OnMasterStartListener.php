<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\OnStart;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Swoole\Server;

/**
 * @Listener
 */
class OnMasterStartListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var StdoutLoggerInterface
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
            OnStart::class,
        ];
    }

    /**
     * @param OnStart $event
     */
    public function process(object $event)
    {
        /** @var Server */
        $server = $event->server;

        $this->stdoutLogger->notice('master: ' . $server->master_pid);
        $this->stdoutLogger->notice('manager: ' . $server->manager_pid);
    }
}
