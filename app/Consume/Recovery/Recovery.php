<?php

declare(strict_types=1);

namespace App\Consume\Recovery;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Notify\Notify;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;
use App\Model\AlarmHistory;
use App\Support\ConditionArr;

class Recovery extends PipelineAbstract
{
    // 条件恢复
    public const MODE_CONDITION = 1;

    // 延迟恢复
    public const MODE_DELAY = 2;

    /**
     * 工作流处理.
     *
     * @return int
     */
    public function handle(Message $message)
    {
        $message->setJump($this->container->get(Compress::class));
        // 未开启顺序执行
        if (! $message->getTask()->isEnableRecovery()) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        return $this->dispatch($message);
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'recovery';
    }

    protected function dispatch(Message $message)
    {
        $recovery = $message->getTask()->getRecovery();
        switch ($recovery['mode']) {
            case static::MODE_CONDITION:
                return $this->recoveryByCondition($message);
            case static::MODE_DELAY:
                return $this->recoveryByDelay($message);
            default:
                // TODO 不支持，记录日志
                return Plant::PIPELINE_STATUS_NEXT;
        }
    }

    protected function recoveryByCondition(Message $message)
    {
        $recovery = $message->getTask()->getRecovery();
        if (ConditionArr::match($recovery['conditions'], $message->getSourcePayload())) {
            // 满足条件，发送恢复通知
            $message->setProp(Notify::MARK_SCENE, Notify::SCENE_RECOVERY);
            $message->setProp(AlarmHistory::ALARM_TYPE, AlarmHistory::RECOVER_ALARM);
            $message->setJump($this->container->get(Notify::class));
            return Plant::PIPELINE_STATUS_JUMP;
        }

        // 不满足条件，继续下一个工作流
        return Plant::PIPELINE_STATUS_NEXT;
    }

    protected function recoveryByDelay(Message $message)
    {
        // 不入库则直接进入下一个流程
        // TODO 记录日志
        if (! $message->getTask()->isSaveDb()) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        // 处理入库的逻辑
        return Plant::PIPELINE_STATUS_NEXT;
    }
}
