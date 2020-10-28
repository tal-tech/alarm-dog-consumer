<?php

declare(strict_types=1);

namespace App\Consume\Compress\Strategy;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Model\AlarmDelayCompresss;
use App\Model\AlarmHistory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis as HyperfRedis;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Redis;
use Throwable;

abstract class StrategyAbstract
{
    // redis缓存的键名前缀，子类注意覆盖
    public const REDIS_CACHE_PREFIX = 'xtq:compress:strategy:-:';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdOutLogger;

    /**
     * @var LoggerInterface
     */
    protected $stractLogger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->redis = $container->get(HyperfRedis::class);
        $this->stdOutLogger = $container->get(StdoutLoggerInterface::class);
        $this->stractLogger = $container->get(LoggerFactory::class)->get('compress_stract');
    }

    /**
     * 处理收敛策略.
     *
     * @param null|string $metric 收敛指标
     */
    abstract public function handle(Message $message, ?string $metric): int;

    /**
     * @param bool $delay
     */
    public function saveDataToDb(Message $message, $delay = false)
    {
        try {
            $model = AlarmHistory::customCreate($message);
            if ($delay) {
                $delayTime = $message->getTask()->getCompress()['strategy_cycle'];
                $data = [
                    'task_id' => $message->getTask()['id'],
                    'metric' => $message->getProp(Compress::MARK_COMPRESS_METRIC, ''),
                    'batch' => $message->getProp(Compress::MARK_COMPRESS_BATCH, 0),
                    'history_id' => $model['id'],
                    'trigger_time' => (time() + $delayTime * 60),
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                AlarmDelayCompresss::create($data);
            }
        } catch (Throwable $e) {
            $msg = 'msg:' . $e->getMessage() . '---data:' . json_encode($message->getSourcePayload());
            $this->stractLogger->error('Save history error, ' . $msg);
            $this->stdOutLogger->error('Save history error, ' . $msg);
        }
    }

    /**
     * 计算收敛批次
     *
     * @return int
     */
    protected function genCompressBatch(?string $metric)
    {
        return sprintf('%u', crc32(sprintf('%s%s%s', $metric, microtime(true), mt_rand())));
    }

    /**
     * 计算收敛批次的缓存key.
     *
     * @return string
     */
    protected function getCompressBatchKey(?string $metric)
    {
        return sprintf('%sbatchid:%s', static::REDIS_CACHE_PREFIX, $metric);
    }

    /**
     * 设置指标过期时间.
     * @param $key
     * @param $expire
     * @return int
     */
    protected function incrAndExpire($key, $expire)
    {
        $luaScript = "
            local key = KEYS[1];
            local expire_time = tonumber(ARGV[1]);
            local num = redis.call('INCR', key);
            if num == 1 then
                redis.call('EXPIRE', key, expire_time);
            end
            return num;
        ";
        return $this->redis->eval($luaScript, [$key, $expire], 1);
    }
}
