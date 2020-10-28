<?php

declare(strict_types=1);

namespace App\Consume\Compress\Strategy;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Pipeline\Plant;

/**
 * 延迟收敛.
 */
class Delay extends StrategyAbstract
{
    // redis缓存的键名前缀
    public const REDIS_CACHE_PREFIX = 'xtq:compress:strategy:delay:';

    /**
     * 处理收敛策略.
     *
     * @param null|string $metric 收敛指标
     */
    public function handle(Message $message, ?string $metric): int
    {
        // 延迟收敛强制不发送告警，交给延迟队列判断是否需要发送告警
        $message->setProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY, true);

        $compress = $message->getTask()->getCompress();
        $metricKey = sprintf('%s%s', static::REDIS_CACHE_PREFIX, $metric);
        $compressCount = $this->incrAndExpire($metricKey, $compress['strategy_cycle'] * 60);

        $delay = false;
        if ($compressCount === 1) {
            // 收敛批次
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = $this->genCompressBatch($metric);
            // 过期时间稍长是避免batch比metric先过期
            $this->redis->setEx($batchKey, $compress['strategy_cycle'] * 60 + 5, $batch);

            // 写入延迟队列数据库 xes_alarm_delay_queue_delay_compress, saveDataToDb中根据delay处理
            $delay = true;
        } else {
            $batchKey = $this->getCompressBatchKey($metric);
            $batch = (int) $this->redis->get($batchKey);
        }

        $message->setProp(Compress::MARK_COMPRESS_BATCH, $batch);

        // 更新batch到history表
        $this->saveDataToDb($message, $delay);

        return Plant::PIPELINE_STATUS_NEXT;
    }
}
