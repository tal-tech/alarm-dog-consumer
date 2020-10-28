<?php

declare(strict_types=1);

namespace App\Consume\Driver;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

abstract class DriverAbstract
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var bool
     */
    protected $running = true;

    /**
     * @var bool
     */
    protected $isReady = false;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct(ContainerInterface $container, $config = [])
    {
        $this->container = $container;
        $this->formatter = $container->get(FormatterInterface::class);
        // $this->logger = $container->get(LoggerFactory::class)->get('mqproxy');
        $this->logger = $container->get(LoggerFactory::class)->get($this->getName());
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
        $this->server = $container->get(Server::class);
        $this->config = $config;
    }

    abstract public function consume(): void;

    abstract public function getName(): string;

    /**
     * @param mixed $data
     */
    abstract public function ack($data): void;

    /**
     * 创建ack finish消息体.
     *
     * @param mixed $data
     */
    abstract public static function buildAckFinish($data): string;

    /**
     * @return mixed
     */
    abstract public function parseAckFinish(string $data);

    /**
     * 是否仍然有未ack的数据.
     */
    abstract public function hasAck(): bool;

    /**
     * 获取消费中的消息数量.
     */
    public function getConsumingCount(): int
    {
        return $this->server->consumingCount->get();
    }

    /**
     * 停止/启动.
     */
    public function setRunning(bool $running = false)
    {
        $this->running = $running;
    }

    public function setReady(bool $ready = true)
    {
        $this->isReady = $ready;
    }

    /**
     * @return bool
     */
    public function getReady()
    {
        return $this->isReady;
    }

    /**
     * @return bool
     */
    protected function canConsume()
    {
        return $this->isReady && $this->running && $this->getConsumingCount() < $this->config['max_consuming_count'];
    }
}
