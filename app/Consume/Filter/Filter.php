<?php

declare(strict_types=1);

namespace App\Consume\Filter;

use App\Consume\Message\Message;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;
use App\Support\ConditionArr;

class Filter extends PipelineAbstract
{
    // 白名单
    public const MODE_WHITELIST = 1;

    // 黑名单
    public const MODE_BLACKLIST = 2;

    // 白名单未命中或黑名单命中-直接入库
    public const NOT_MATCH_SAVEDB = 1;

    // 白名单未命中或黑名单命中-丢弃告警
    public const NOT_MATCH_DISCARD = 0;

    /**
     * 工作流处理.
     *
     * @return int
     */
    public function handle(Message $message)
    {
        // 未开启顺序执行
        if (! $message->getTask()->isEnableFilter()) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        return $this->dispatch($message);
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'filter';
    }

    protected function dispatch(Message $message)
    {
        $filter = $message->getTask()->getFilter();
        $matched = ConditionArr::match($filter['conditions'], $message->getSourcePayload());

        switch ($filter['mode']) {
            case static::MODE_WHITELIST:
                // 白名单且命中，入库后继续下一流程
                if ($matched) {
                    //判断是否入库
                    return Plant::PIPELINE_STATUS_NEXT;
                }
                // 白名单且未命中，丢弃告警
                return $this->discardAlarm($message);
            case static::MODE_BLACKLIST:
                // 黑名单且未命中，入库后继续下一个流程
                if (! $matched) {
                    return Plant::PIPELINE_STATUS_NEXT;
                }
                // 黑名单且命中，丢弃告警
                return $this->discardAlarm($message);
            default:
                // 记录日志，不规范枚举
                // TODO
                return Plant::PIPELINE_STATUS_NEXT;
        }
    }

    /**
     * 丢弃告警.
     * @return int
     */
    protected function discardAlarm(Message $message)
    {
        $notMatch = $message->getTask()->getFilter()['not_match'];
        // 入库开启且明确入库的，保存到数据库
        if ($message->getTask()->isSaveDb() && $notMatch == static::NOT_MATCH_SAVEDB) {
            $this->saveDb($message);
        }

        return Plant::PIPELINE_STATUS_END;
    }
}
