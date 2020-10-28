<?php

declare(strict_types=1);

namespace App\Consume\Process;

use App\Consume\Upgrade\UpgradeHandle;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

/**
 * 消费异常检测.
 */
class AlarmUpgradeProcess extends AbstractProcess
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
    public $name = 'alarm-upgrade';

    /**
     * @var UpgradeHandle
     */
    private $upgradeHandle;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->upgradeHandle = $this->container->get(UpgradeHandle::class);
    }

    public function handle(): void
    {
        $this->upgradeHandle->start();
    }

    public function isEnable($server): bool
    {
        return strpos(config('start_processes', ''), 'upgrade') !== false;
    }
}
