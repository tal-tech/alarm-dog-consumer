<?php

declare(strict_types=1);

namespace App\Consume\Compress;

use App\Consume\Compress\Method\Condition;
use App\Consume\Compress\Method\Content;
use App\Consume\Compress\Method\Full;
use App\Consume\Compress\Method\MethodAbstract;
use App\Consume\Compress\Strategy\Cycle;
use App\Consume\Compress\Strategy\CycleTimes;
use App\Consume\Compress\Strategy\Delay;
use App\Consume\Compress\Strategy\StrategyAbstract;
use App\Consume\Compress\Strategy\Times;
use App\Consume\Compress\Strategy\TimesCycle;
use App\Consume\Message\Message;
use App\Consume\Notify\Notify;
use App\Consume\Pipeline\JumpException;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;

class Compress extends PipelineAbstract
{
    // 标记消息是否命中收敛
    public const MARK_COMPRESS_METRIC = 'compressMetric';

    // 标记消息的收敛批次
    public const MARK_COMPRESS_BATCH = 'compressBatch';

    // 标记消息被收敛忽略告警发送
    public const MARK_COMPRESS_IGNORE_NOTIFY = 'compressIgnoreNotify';

    /**
     * 收敛方式.
     */
    // 条件收敛
    public const METHOD_CONDITION = 1;

    // 内容收敛
    public const METHOD_CONTENT = 3;

    // 全量收敛
    public const METHOD_FULL = 4;

    /**
     * 收敛策略.
     */
    // 周期收敛
    public const STRATEGY_CYCLE = 1;

    // 延迟收敛
    public const STRATEGY_DELAY = 2;

    // 周期次数收敛
    public const STRATEGY_CYCLE_TIMES = 3;

    // 次数周期收敛
    public const STRATEGY_TIMES_CYCLE = 4;

    // 次数收敛
    public const STRATEGY_TIMES = 5;

    /**
     * 收敛方式的handlers.
     *
     * @var MethodAbstract[]
     */
    protected static $methodHandlers = [];

    /**
     * 收敛策略的handlers.
     *
     * @var StrategyAbstract[]
     */
    protected static $strategyHandlers = [];

    /**
     * 工作流处理.
     *
     * @throws JumpException
     * @return int
     */
    public function handle(Message $message)
    {
        // 标记默认未收敛
        $message->setProp(Notify::MARK_SCENE, Notify::SCENE_NOT_COMPRESS);
        // 默认标记告警指标为空，在收敛的地方进行覆盖
        $message->setProp(static::MARK_COMPRESS_METRIC, null);

        // 未开启 无法使用收敛，顺序执行
        if (! $message->getTask()->isSaveDb()) {
            $message->setProp('context', ['type' => 'not_save_db']);
            return Plant::PIPELINE_STATUS_NEXT;
        }

        // 未开启收敛，无法使用收敛，顺序执行
        if (! $message->getTask()->isEnableCompress()) {
            $message->setProp('context', ['type' => 'compress_disable']);
            return Plant::PIPELINE_STATUS_NEXT;
        }

        return $this->dispatch($message);
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'compress';
    }

    /**
     * 初始化.
     */
    protected function init()
    {
        // 收敛方式初始化
        static::$methodHandlers = [
            static::METHOD_CONDITION => make(Condition::class),
            static::METHOD_CONTENT => make(Content::class),
            static::METHOD_FULL => make(Full::class),
        ];

        // 收敛策略初始化
        static::$strategyHandlers = [
            static::STRATEGY_CYCLE => make(Cycle::class),
            static::STRATEGY_DELAY => make(Delay::class),
            static::STRATEGY_CYCLE_TIMES => make(CycleTimes::class),
            static::STRATEGY_TIMES_CYCLE => make(TimesCycle::class),
            static::STRATEGY_TIMES => make(Times::class),
        ];
    }

    /**
     * @throws JumpException
     * @return int
     */
    protected function dispatch(Message $message)
    {
        $metric = $this->compressMetric($message);

        // 未命中处理
        if (is_null($metric)) {
            return $this->handleUnCompress($message);
        }

        // 命中处理
        return $this->dispatchToStrategy($message, $metric);
    }

    /**
     * 计算收敛指标.
     *
     * @throws JumpException
     * @return null|string 为null表示未命中收敛
     */
    protected function compressMetric(Message $message)
    {
        $method = $message->getTask()->getCompress()['method'];
        if (isset(static::$methodHandlers[$method])) {
            return static::$methodHandlers[$method]->handle($message);
        }
        // 不支持的收敛方式，直接终止收敛的逻辑，进入下一个工作流
        // TODO 记录日志
        throw new JumpException(Plant::PIPELINE_STATUS_NEXT);
    }

    /**
     * 处理未命中收敛告警.
     * @return int
     */
    protected function handleUnCompress(Message $message)
    {
        // 告警以未收敛方式发送
        $message->setProp(Notify::MARK_SCENE, Notify::SCENE_NOT_COMPRESS);

        if ($message->getTask()->getCompress()['not_match']) {
            // 直接发送，并且以未收敛的模板发送，但不终止工作流
            $message->setProp('context', ['type' => 'compress_not_match']);
            return Plant::PIPELINE_STATUS_NEXT;
        }
        // 丢弃告警，跳转到直接发送告警
        $message->setJump($this->container->get(Notify::class));
        // 直接丢弃吗? 不应该直接结束吗？ Plant::PIPELINE_STATUS_JUMP => Plant::PIPELINE_STATUS_END
            $this->saveDb($message); // 入库操作
            return Plant::PIPELINE_STATUS_END;
    }

    /**
     * 将收敛告警调度到收敛策略.
     * @param $metric
     * @throws JumpException
     * @return int
     */
    protected function dispatchToStrategy(Message $message, $metric)
    {
        // 标记告警已命中收敛，通知模板为收敛
        $message->setProp(static::MARK_COMPRESS_METRIC, $metric);
        $message->setProp(Notify::MARK_SCENE, Notify::SCENE_COMPRESSED);

        $strategy = $message->getTask()->getCompress()->getStratrgy();
        if (isset(static::$strategyHandlers[$strategy])) {
            $message->setProp('context', ['type' => 'compressed']);
            return static::$strategyHandlers[$strategy]->handle($message, $metric);
        }
        // 不支持的收敛策略，直接终止收敛的逻辑，进入下一个工作流
        // TODO 记录日志
        $message->setProp(static::MARK_COMPRESS_METRIC, null);
        throw new JumpException(Plant::PIPELINE_STATUS_NEXT);
    }
}
