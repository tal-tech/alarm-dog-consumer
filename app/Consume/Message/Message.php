<?php

declare(strict_types=1);

namespace App\Consume\Message;

use App\Consume\Pipeline\PipelineAbstract;
use App\Model\AlarmHistory;

/**
 * 消费的消息对象
 */
class Message
{
    /**
     * 消息状态枚举.
     */
    // 开始
    public const STATUS_START = 0;

    // 继续消费
    public const STATUS_CONTINUE = 1;

    // 跳转到指定pipeline
    public const STATUS_JUMP = 2;

    // 回到上一个状态继续消费
    public const STATUS_BACK = 3;

    // 终止
    public const STATUS_END = 9;

    public const IS_SAVE_DB = 'SAVE_DB';

    /**
     * 载荷.
     *
     * @var array
     */
    protected $payload = [];

    /**
     * 最原始的载荷，永不修改.
     *
     * @var array
     */
    protected $sourcePayload = [];

    /**
     * 告警任务
     *
     * @var Task
     */
    protected $task;

    /**
     * 额外属性.
     *
     * @var array
     */
    protected $props = [];

    /**
     * 消息状态
     *
     * @var int
     */
    protected $status = self::STATUS_START;

    /**
     * 上次执行工作流
     *
     * @var PipelineAbstract
     */
    protected $lastPipeline;

    /**
     * 跳转工作流对象
     *
     * @var PipelineAbstract
     */
    protected $jumpToPipeline;

    /**
     * 跳转之后恢复执行的工作流
     *
     * @var PipelineAbstract
     */
    protected $recoveryPointPipeline;

    /**
     * @var AlarmHistory
     */
    protected $alarmHistory;

    public function __construct(array $payload, Task $task)
    {
        $this->payload = $payload;
        $this->sourcePayload = $payload;
        $this->task = $task;
        $this->props = [
            AlarmHistory::ALARM_TYPE => AlarmHistory::NORMAL_ALARM,
        ];
    }

    /**
     * 设置属性.
     * @param $value
     */
    public function setProp(string $name, $value): Message
    {
        $this->props[$name] = $value;

        return $this;
    }

    /**
     * 设置多个属性.
     */
    public function setProps(array $props): Message
    {
        foreach ($props as $name => $value) {
            $this->setProp($name, $value);
        }

        return $this;
    }

    /**
     * 获取属性.
     * @param null $default
     * @return null|mixed
     */
    public function getProp(string $name, $default = null)
    {
        return $this->props[$name] ?? $default;
    }

    /**
     * 获取所有属性.
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * 设置状态
     * @return Message
     */
    public function setStatus(int $status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * 设置跳转.
     * @return Message
     */
    public function setJump(PipelineAbstract $jumpToPipeline)
    {
        $this->setStatus(self::STATUS_JUMP);
        $this->jumpToPipeline = $jumpToPipeline;

        return $this;
    }

    /**
     * 获取状态
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param array $payload 载荷
     * @return self
     */
    public function setPayload(array $payload)
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @return array
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @return array
     */
    public function getSourcePayload()
    {
        return $this->sourcePayload;
    }

    /**
     * @return PipelineAbstract
     */
    public function getJumpToPipeline()
    {
        return $this->jumpToPipeline;
    }

    public function setAlarmHistory(AlarmHistory $alarmHistory)
    {
        $this->alarmHistory = $alarmHistory;
    }

    public function getAlarmHistory()
    {
        return $this->alarmHistory;
    }

    /**
     * 保存告警到数据库.
     */
    public function saveDb()
    {
        return AlarmHistory::customCreate($this);
    }
}
