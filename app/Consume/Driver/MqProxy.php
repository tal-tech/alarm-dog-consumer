<?php

declare(strict_types=1);

namespace App\Consume\Driver;

use App\Exception\AppException;
use App\Support\GuzzleCreator;
use GuzzleHttp\Client;
use Psr\Container\ContainerInterface;
use Swoole\Atomic;
use Swoole\Timer;
use Throwable;

class MqProxy extends DriverAbstract
{
    public const URI_CONSUME_KAFKA = '/v1/kafka/fetch';

    public const URI_COMMIT_KAFKA = '/v1/kafka/commit';

    /**
     * @var Client
     */
    protected $client;

    /**
     * offset池，用于定时提交offset.
     *
     * @var array
     */
    protected $offsetPool = [];

    /**
     * offset池commit锁
     *
     * @var bool
     */
    protected $offsetCommitLock = false;

    /**
     * 临时的offset池，用于poll被锁时临时存储offset.
     *
     * @var array
     */
    protected $tempOffsetPool = [];

    /**
     * 上次提交时间.
     *
     * @var float
     */
    protected $lastCommitTime = 0.0;

    /**
     * @var bool
     */
    protected $committing = false;

    public function __construct(ContainerInterface $container, $config = [])
    {
        parent::__construct($container, $config);

        mt_srand(time());
        $config['guzzle']['options']['base_uri'] = $config['proxy'][mt_rand(0, count($config['proxy']) - 1)];
        $this->client = GuzzleCreator::create($config['guzzle']);

        unset($config['guzzle']);
        $this->config = $config;

        $this->resetOffsetPool();

        $this->startAutoCommit();
    }

    /**
     * 消费.
     */
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
                $data = $this->fetch();
            } catch (Throwable $e) {
                usleep($usleepTime);
                continue;
            }

            // 数据条数为0时，自动等待防止空轮询
            if (count($data['data']) === 0) {
                usleep($usleepTime);
                continue;
            }

            // 投递任务到task
            foreach ($data['data'] as $item) {
                try {
                    $payload = json_decode($item['payload'], true);
                    // 不符合格式要求的数据直接ack，避免重复消费
                    if (! is_array($payload) || ! isset($payload['taskid']) || ! isset($payload['ctn'])) {
                        $this->ack(['partition' => $item['props']['partition'], 'offset' => $item['props']['offset']]);
                        continue;
                    }

                    unset($item['payload']);
                    $message = [
                        'driver' => __CLASS__,
                        'payload' => $payload,
                        'extra' => $item,
                    ];
                    $consumingCount->add(1);
                    $this->server->task($message);
                } catch (Throwable $e) {
                    // 此处忽略异常
                    $this->logger->warning($this->formatter->format($e));
                }
            }

            // 会有异步协程去自动commit offsets
            // do nothing
        }
    }

    public function getName(): string
    {
        return 'mqproxy';
    }

    /**
     * 提交ack.
     *
     * @param mixed $data 为了兼容其他redis等driver，此处参数写活
     *                    ['partition' => 0, 'offset' => 0]
     */
    public function ack($data): void
    {
        if ($this->offsetCommitLock) {
            $this->tempOffsetPool[] = $data;
        } else {
            array_push($this->offsetPool[$data['partition']], $data['offset']);
        }
    }

    /**
     * 创建ack finish消息体.
     *
     * @param mixed $data
     */
    public static function buildAckFinish($data): string
    {
        return json_encode(
            [
                'partition' => $data['extra']['props']['partition'],
                'offset' => $data['extra']['props']['offset'],
            ]
        );
    }

    /**
     * @return mixed
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
        return ! empty($this->offsetPool) || ! empty($this->tempOffsetPool) || $this->commiting;
    }

    /**
     * 重置offsetPool.
     */
    protected function resetOffsetPool()
    {
        foreach (range(0, $this->config['max_partition_size']) as $num) {
            $this->offsetPool[$num] = [];
        }
    }

    /**
     * 重置临时offsetPool.
     */
    protected function resetTempOffsetPool()
    {
        $this->tempOffsetPool = [];
    }

    /**
     * @return array
     */
    protected function getOffsetPool()
    {
        // 先加锁，避免协程影响
        $this->offsetCommitLock = true;
        $offsetPool = $this->offsetPool;
        $this->resetOffsetPool();
        $this->offsetCommitLock = false;

        return $offsetPool;
    }

    /**
     * 合并临时存储.
     * @param mixed $offsetPool
     */
    protected function mergeTempOffsetPool(&$offsetPool)
    {
        foreach ($this->tempOffsetPool as $data) {
            array_push($offsetPool[$data['partition']], $data['offset']);
        }
        $this->resetTempOffsetPool();
    }

    /**
     * @return array
     */
    protected function sortOffset()
    {
        $offsetPool = $this->getOffsetPool();

        // 将tempOffsetPool中数据合并到$offsetPool中
        $this->mergeTempOffsetPool($offsetPool);

        $offsetsToCommit = [];
        foreach ($offsetPool as $partition => $offsets) {
            // 为了加速markOffset，存在预先设置的空partition，此处忽略
            if (empty($offsets)) {
                continue;
            }
            sort($offsets, SORT_NUMERIC);
            $oCount = count($offsets);
            $start = $offsets[0];
            for ($i = 0; $i < $oCount; $i++) {
                $next = $i + 1;
                if ($next != $oCount && $offsets[$next] != $offsets[$i] + 1) {
                    // 区间不连续
                    $offsetsToCommit[] = [
                        'topic' => $this->config['topic'],
                        'partition' => $partition,
                        'left' => $start,
                        'right' => $offsets[$i],
                    ];
                    $start = $offsets[$next];
                }
            }
            $offsetsToCommit[] = [
                'topic' => $this->config['topic'],
                'partition' => $partition,
                'left' => $start,
                'right' => $offsets[$oCount - 1],
            ];
        }

        return $offsetsToCommit;
    }

    /**
     * 获取MQ消息.
     * @throws Throwable
     */
    protected function fetch()
    {
        $json = [
            'group' => $this->config['group'],
            'queues' => $this->config['topic'],
            'reset' => $this->config['reset'],
            'commitTimeout' => $this->config['commit_timeout'],
            'maxConsumeTimes' => $this->config['max_consume_times'],
            'maxMsgs' => $this->config['max_msgs'],
        ];

        return $this->sendRequest(static::URI_CONSUME_KAFKA, $json);
    }

    /**
     * 提交offset.
     */
    protected function commit()
    {
        $this->commiting = true;

        $sortedOffset = $this->sortOffset();
        // 没有需要commit的offset时终止commit
        if (empty($sortedOffset)) {
            $this->commiting = false;
            return;
        }
        $json = [
            'group' => $this->config['group'],
            'queues' => $this->config['topic'],
            'data' => $sortedOffset,
        ];

        // dump('commit: ' . json_encode($sortedOffset));

        $data = $this->sendRequest(static::URI_COMMIT_KAFKA, $json);

        $this->commiting = false;
        $this->lastCommitTime = microtime(true);

        return $data;
    }

    /**
     * 生成网关认证Header.
     *
     * @return array
     */
    protected function genGatewayHeaders()
    {
        $timestamp = time();

        return [
            'X-Auth-Appid' => $this->config['appid'],
            'X-Auth-TimeStamp' => $timestamp,
            'X-Auth-Sign' => md5($this->config['appid'] . '&' . $timestamp . $this->config['appkey']),
        ];
    }

    /**
     * 发送请求
     *
     * @throws Throwable
     * @return array
     */
    protected function sendRequest(string $uri, array $json)
    {
        try {
            $resp = $this->client->post(
                $uri,
                [
                    'json' => $json,
                    'headers' => $this->genGatewayHeaders(),
                ]
            );

            if (($statusCode = $resp->getStatusCode()) != 200) {
                throw new AppException(
                    sprintf('status code is %d', $statusCode),
                    [
                        'status_code' => $statusCode,
                    ]
                );
            }

            $body = (string) $resp->getBody()->getContents();
            $json = json_decode($body, true);
            if (! is_array($json) || ! isset($json['code'])) {
                throw new AppException(sprintf('invalid response body: %s', mb_substr($body, 0, 500)));
            }

            if ($json['code'] != 0) {
                $msg = $json['msg'] ?? 'unknown';
                throw new AppException(
                    sprintf('occur error: %s(%s)', $msg, $json['code']),
                    [
                        'code' => $json['code'],
                    ]
                );
            }

            return $json;
        } catch (Throwable $e) {
            // 因guzzle不支持重新设置base_uri，此处异常直接抛出
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * 开启自动commit.
     */
    protected function startAutoCommit()
    {
        $interval = 1000;
        Timer::tick(
            $interval,
            function () {
                try {
                    $this->commit();
                } catch (Throwable $e) {
                    $this->logger->warning($this->formatter->format($e));
                }
            }
        );
    }
}
