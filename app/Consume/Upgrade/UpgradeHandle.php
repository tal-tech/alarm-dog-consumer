<?php

declare(strict_types=1);

namespace App\Consume\Upgrade;

use App\Consume\Notify\Notify;
use App\Consume\Process\AbstractHandle;
use App\Model\AlarmHistory;
use App\Model\AlarmTemplate as AlarmTpl;
use Swoole\Coroutine;
use Throwable;

class UpgradeHandle extends AbstractHandle
{
    protected $name = 'alarm-upgrade';

    public function start()
    {
        $nextTime = 10;
        do {
            try {
                $items = $this->redis->zRangeByScore(
                    Upgrade::REDIS_CACHE_PREFIX,
                    '-inf',
                    (string) (time() + 60),
                    ['withscores' => true]
                );
                // 任务为空
                if (! $items) {
                    $this->stdoutLogger->info('upgrade empty sleep, next time:' . $nextTime);
                    Coroutine::sleep($nextTime);
                    continue;
                }
                $nextTime = 10;
                foreach ($items as $item => $score) {
                    if (time() < $score) {
                        break;
                    }
                    [$taskId, $metric, $interval, $count] = explode('.', $item);
                    // 获取规则
                    $strategies = $this->cache->getTask($taskId)->getUpgrade()->getStrategies();
                    $cycleKey = Upgrade::getAlarmUpgradeKey((int) $taskId, $metric);
                    $timeArr = explode(' ', microtime());
                    $this->stdoutLogger->info("触发····{$item}");
                    $this->redis->zRem(Upgrade::REDIS_CACHE_PREFIX, $item);
                    $currentRule = $strategies[$interval . '.' . $count];
                    $zCount = $this->redis->zCount(
                        $cycleKey,
                        (string) (($timeArr[1] - $interval * 60) + $timeArr[0]),
                        (string) ($timeArr[1] + $timeArr[0])
                    );
                    if ($zCount >= $count) {
                        $historyObj = AlarmHistory::query()
                            ->where('metric', $metric)
                            ->orderByDesc('id')->first();
                        if (! is_null($historyObj)) {
                            $historyArr = $historyObj->toArray();
                            // 投递给task处理
                            $this->server->task(
                                [
                                    Notify::MARK_SCENE => AlarmTpl::SCENE_UPGRADE,
                                    'payload' => $historyArr,
                                    'context' => [
                                        'rule' => $currentRule,
                                        'zcount' => $zCount,
                                    ],
                                    'pipeline' => Notify::class,
                                ]
                            );
                        }
                    } else {
                        $diff = $score - time();
                        $nextTime = max($diff, 10);
                        // 清理历史记录
                        if (! empty($strategies)) {
                            $maxInterval = max(array_column($strategies, 'interval'));
                            // 移除过期的数据
                            $this->redis->zRemRangeByScore(
                                $cycleKey,
                                '-inf',
                                (string) (($timeArr[1] - $maxInterval * 60) + $timeArr[0])
                            );
                        }
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error('Update rule of upgrade error! ' . $e->getMessage());
                $this->stdoutLogger->error('Update rule of upgrade error! ' . $e->getMessage());
                Coroutine::sleep(20);
            }
        } while (true);
    }
}
