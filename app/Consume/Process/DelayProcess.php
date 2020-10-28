<?php

declare(strict_types=1);

namespace App\Consume\Process;

use App\Consume\Compress\DelayHandle;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

/**
 * 消费异常检测.
 */
class DelayProcess extends AbstractProcess
{
    public const WAIT_BEFORE_RECONNECT_US = 2000000;

    /**
     * 进程数量.
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称.
     * @var string
     */
    public $name = 'alarm-delay';

    /**
     * @var DelayHandle
     */
    private $delayHandle;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->delayHandle = $this->container->get(DelayHandle::class);
    }

    public function handle(): void
    {
        $this->delayHandle->start();
    }

    public function isEnable($server): bool
    {
        return strpos(config('start_processes', ''), 'delay') !== false;
    }
}
