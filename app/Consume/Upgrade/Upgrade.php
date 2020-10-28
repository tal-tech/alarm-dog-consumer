<?php

declare(strict_types=1);

namespace App\Consume\Upgrade;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;
use App\Model\AlarmUpgradeMetric;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis as HyperfRedis;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Throwable;

class Upgrade extends PipelineAbstract
{
    // redis缓存的键名前缀
    public const REDIS_CACHE_PREFIX = 'xtq:compress:upgrade:';

    /**
     * @var HyperfRedis
     */
    protected $redis;

    /**
     * @var LoggerInterface
     */
    protected $saveRuleLogger;

    /**
     * @var LoggerInterface
     */
    protected $upgradeLogger;

    /**
     * @var StdoutLoggerInterface
     */
    private $stdoutLogger;

    public function init()
    {
        $this->redis = $this->container->get(HyperfRedis::class);
        $this->stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->upgradeLogger = $this->container->get(LoggerFactory::class)->get('alarm_upgrade');
        $this->saveRuleLogger = $this->container->get(LoggerFactory::class)->get('alarm_upgrade_save_rule');
    }

    /**
     * 工作流处理.
     *
     * @return int
     */
    public function handle(Message $message)
    {
        // 未开启或者未收敛，无法使用升级，顺序执行
        if (! $message->getTask()->isEnableUpgrade() || ! $message->getProp(Compress::MARK_COMPRESS_METRIC)) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        return $this->dispatch($message);
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'upgrade';
    }

    /**
     * 告警升级.
     */
    public function alarmUpgrade(Message $message)
    {
        $taskId = $message->getTask()['id'];
        $metric = $message->getProp(Compress::MARK_COMPRESS_METRIC);
        $strategies = $message->getTask()->getUpgrade()->getStrategies();
        try {
            //保存统计信息
            Coroutine::create(
                function () use ($taskId, $metric) {
                    try {
                        $key = self::getAlarmUpgradeKey($taskId, $metric);
                        $arr = explode(' ', microtime());
                        $val = $arr[1] + $arr[0];
                        $this->redis->zAdd($key, $val, $val);
                        $this->redis->expire($key, 2 * 24 * 60 * 60); // 48小时
                    } catch (Throwable $e) {
                        $this->stdoutLogger->notice($e->getMessage());
                        $this->upgradeLogger->notice('To redis, error:' . $e->getMessage());
                    }
                }
            );

            //保存规则
            Coroutine::create(
                function () use ($taskId, $metric, $strategies) {
                    try {
                        foreach ($strategies as $strategy) {
                            $interval = $strategy['interval'];
                            $count = $strategy['count'];
                            $value = $taskId . '.' . $metric . '.' . $interval . '.' . $count;
                            $this->redis->zAdd(self::REDIS_CACHE_PREFIX, ['NX'], time() + $interval * 60, $value);
                        }
                    } catch (Throwable $e) {
                        $this->stdoutLogger->notice($e->getMessage());
                        $this->saveRuleLogger->notice('Save rule to redis error:' . $e->getMessage());
                    }
                }
            );
        } catch (Throwable $e) {
            $this->upgradeLogger->error('save upgrade rule false msg:' . $e->getMessage());
        }
    }

    /**
     * @return string
     */
    public static function getAlarmUpgradeKey(int $taskId, string $metric)
    {
        return self::REDIS_CACHE_PREFIX . $taskId . ':' . $metric;
    }

    protected function dispatch(Message $message)
    {
        // 记录升级告警数据
        $this->alarmUpgrade($message);

        return Plant::PIPELINE_STATUS_NEXT;
    }

    /**
     * 保存到数据库
     * TODO: 暂未使用.
     */
    protected function createAlarmUpgradeKey(int $taskId, string $metric)
    {
        $data = [
            'task_id' => $taskId,
            'metric' => $metric,
            'created_at' => time(),
        ];
        Coroutine::create(
            function () use ($data) {
                try {
                    AlarmUpgradeMetric::create($data);
                } catch (Throwable $e) {
                    $this->upgradeLogger->error($e->getMessage());
                }
            }
        );
    }
}
