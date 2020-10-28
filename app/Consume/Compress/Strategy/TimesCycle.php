<?php

declare(strict_types=1);

namespace App\Consume\Compress\Strategy;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Pipeline\Plant;

/**
 * 次数周期收敛.
 */
class TimesCycle extends StrategyAbstract
{
    // redis缓存的键名前缀
    public const REDIS_CACHE_PREFIX = 'xtq:compress:strategy:timescycle:';

    /**
     * 处理收敛策略.
     *
     * @param null|string $metric 收敛指标
     */
    public function handle(Message $message, ?string $metric): int
    {
        // 默认先忽略告警，下面需要告警的地方覆盖
        $message->setProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY, true);

        $compress = $message->getTask()->getCompress();
        $metricKey = sprintf('%s%s', static::REDIS_CACHE_PREFIX, $metric);
        $compressCount = $this->incrAndExpire($metricKey, $compress['strategy_cycle'] * 60);

        if ($compressCount === 1) {
            // 收敛批次
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = $this->genCompressBatch($metric);
            // 过期时间稍长是避免batch比metric先过期
            $this->redis->setEx($batchKey, $compress['strategy_cycle'] * 60 + 5, $batch);
        } else {
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = (int) $this->redis->get($batchKey);

            // 判断是否达到次数需要重置周期，并发生告警
            if ($compressCount >= $compress['strategy_count']) {
                $message->setProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY, false);
                $this->redis->del($metricKey, $batchKey);
            }
        }

        $message->setProp(Compress::MARK_COMPRESS_BATCH, $batch);

        // 批次更新到history表
        $this->saveDataToDb($message);
        return Plant::PIPELINE_STATUS_NEXT;
    }
}
