<?php

declare(strict_types=1);

namespace App\Consume\Compress\Strategy;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Pipeline\Plant;

/**
 * 周期收敛.
 */
class Cycle extends StrategyAbstract
{
    // redis缓存的键名前缀
    public const REDIS_CACHE_PREFIX = 'xtq:compress:strategy:cycle:';

    /**
     * 处理收敛策略.
     *
     * @param null|string $metric 收敛指标
     */
    public function handle(Message $message, ?string $metric): int
    {
        $compress = $message->getTask()->getCompress();
        $metricKey = sprintf('%s%s', static::REDIS_CACHE_PREFIX, $metric);
        $compressCount = $this->incrAndExpire($metricKey, $compress['strategy_cycle'] * 60);

        if ($compressCount === 1) {
            // 收敛批次
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = $this->genCompressBatch($metric);
            // 过期时间稍长是避免batch比metric先过期
            $this->redis->setEx($batchKey, $compress['strategy_cycle'] * 60 + 5, $batch);

        // 发送告警，无需处理，进入下一步工作流
            // do nothing
        } else {
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = (int) $this->redis->get($batchKey);

            // 被收敛，不发送告警，标记在消息上
            $message->setProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY, true);
        }

        $message->setProp(Compress::MARK_COMPRESS_BATCH, $batch);

        // 批次更新到history表
        $this->saveDataToDb($message);
        return Plant::PIPELINE_STATUS_NEXT;
    }
}
