<?php

declare(strict_types=1);

namespace App\Consume\Process;

use App\Consume\Workflow\WorkflowHandle;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;

/**
 * 消费异常检测.
 */
class WorkflowProcess extends AbstractProcess
{
    /**
     * 进程数量.
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称.
     * @var string
     */
    public $name = 'alarm-workflow';

    /**
     * @var WorkflowHandle
     */
    private $workflowHandle;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->workflowHandle = $this->container->get(WorkflowHandle::class);
    }

    public function handle(): void
    {
        $this->workflowHandle->start();
    }

    public function isEnable($server): bool
    {
        return strpos(config('start_processes', ''), 'workflow') !== false;
    }
}
