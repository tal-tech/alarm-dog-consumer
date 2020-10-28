<?php

declare(strict_types=1);

namespace App\Consume\Driver;

use Hyperf\Redis\Redis as HyperfRedis;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use Swoole\Atomic;
use Throwable;

class Redis extends DriverAbstract
{
    /**
     * @var HyperfRedis
     */
    protected $redis;

    protected $waitForAck = [];

    public function __construct(ContainerInterface $container, $config = [])
    {
        parent::__construct($container, $config);

        $this->redis = $container->get(RedisFactory::class)->get($config['instance']);
    }

    public function consume(): void
    {
        /** @var Atomic */
        $consumingCount = $this->server->consumingCount;
        // 100ms
        $usleepTime = 100000;
        while (true) {
            // 不满足继续消费条件时，sleep
            if (! $this->canConsume()) {
                usleep($usleepTime);
                continue;
            }

            try {
                //获取任务
                $message = $this->redis->brPop($this->config['key_message'], $this->config['timeout']);
                if (empty($message)) {
                    usleep($usleepTime);
                    continue;
                }
                [$key, $payload] = $message;
                //把任务放入zset，实现ack机制
                $this->redis->zAdd($this->config['key_consuming'], microtime(true), $payload);
            } catch (Throwable $e) {
                $this->logger->info($this->formatter->format($e));
                continue;
            }

            try {
                $payload = json_decode($payload, true);
                // 不符合格式要求的数据直接ack，避免重复消费
                if (! is_array($payload) || ! isset($payload['taskid']) || ! isset($payload['ctn'])) {
                    $this->ack($payload);
                    continue;
                }
                $this->waitForAck[$payload['uuid']] = true;
                $message = [
                    'driver' => __CLASS__,
                    'payload' => $payload,
                ];
                $consumingCount->add(1);
                $this->server->task($message);
            } catch (Throwable $e) {
                // 此处忽略异常
                $this->logger->warning($this->formatter->format($e));
            }
        }
    }

    public function getName(): string
    {
        return 'redis';
    }

    /**
     * @param mixed $payload
     */
    public function ack($payload): void
    {
        $this->redis->zRem($this->config['key_consuming'], json_encode($payload));
        unset($this->waitForAck[$payload['uuid']]);
    }

    /**
     * 创建ack finish消息体.
     *
     * @param mixed $data
     */
    public static function buildAckFinish($data): string
    {
        return json_encode($data['payload']);
    }

    /**
     * @return array
     */
    public function parseAckFinish(string $data)
    {
        return json_decode($data, true);
    }

    /**
     * 是否仍然有未ack的数据.
     */
    public function hasAck(): bool
    {
        return ! empty($this->waitForAck);
    }
}
