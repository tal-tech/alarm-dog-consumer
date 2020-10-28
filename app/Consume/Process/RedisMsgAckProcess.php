<?php

declare(strict_types=1);

namespace App\Consume\Process;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Redis\Redis;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Event;
use Swoole\Timer;
use Throwable;

/**
 * 消费异常检测.
 */
class RedisMsgAckProcess extends AbstractProcess
{
    /**
     * 进程数量.
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称.
     * @var string
     */
    public $name = 'redis-msg-ack';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var array
     */
    private $consumerConfig;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->logger = $this->container->get(LoggerFactory::class)->get('redisAckCheck');
        $this->formatter = $this->container->get(FormatterInterface::class);
        $this->stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->config = $this->container->get(ConfigInterface::class);
    }

    public function handle(): void
    {
        //获取redis配置
        $this->consumerConfig = $this->config->get('consumer.drivers.redis.config');
        $this->redis = $this->container->get(RedisFactory::class)->get($this->consumerConfig['instance']);

        $this->stdoutLogger->info($this->name . ' start');
        $interval = (int) $this->consumerConfig['check_ack_interval'] ?? 10000;
        Timer::tick(
            $interval,
            function () {
                $this->checkAck();
            }
        );

        Event::wait();
    }

    public function isEnable($server): bool
    {
        // 根据consumer配置，判断是否跟随启动
        $driver = $this->config->get('consumer.driver');
        return strpos($driver, 'redis') !== false;
    }

    /**
     * 数据同步.
     *
     * @return bool 同步成功返回true，否则false
     */
    protected function checkAck()
    {
        try {
            $payloads = $this->redis->zRangeByScore(
                $this->consumerConfig['key_consuming'],
                (string) 0,
                (string) (time() - 120)
            );
            if (! $payloads) {
                return false;
            }
            $this->redis->multi();
            foreach ($payloads as $payload) {
                $this->redis->zRem($this->consumerConfig['key_consuming'], $payload);
                $this->redis->rPush($this->consumerConfig['key_message'], $payload);
                $this->logger->warning('消费异常: ' . $payload);
            }
            $this->redis->exec();
        } catch (Throwable $e) {
            // 记录异常日志
            $this->logger->warning($this->formatter->format($e));
            return false;
        }
    }
}
