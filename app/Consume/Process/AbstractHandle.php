<?php

declare(strict_types=1);

namespace App\Consume\Process;

use App\Consume\Cache\CacheInterface;
use App\Model\AlarmGroup;
use App\Model\AlarmTask;
use App\Model\AlarmTemplate;
use App\Model\User;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server;

abstract class AbstractHandle
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
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var mixed|Redis
     */
    protected $redis;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var mixed|Server
     */
    protected $server;

    protected $name;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $this->container->get(LoggerFactory::class)->get($this->name);
        $this->formatter = $this->container->get(FormatterInterface::class);
        $this->stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->config = $this->container->get(ConfigInterface::class);
        $this->redis = $this->container->get(Redis::class);
        $this->cache = $this->container->get(CacheInterface::class);
        $this->server = $container->get(Server::class);
        $this->init();
        $interval = (int) $this->config->get('consumer.data_sync_interval', 60000);
        \Swoole\Timer::tick(
            $interval,
            function () {
                $this->init();
            }
        );
    }

    abstract public function start();

    protected function init()
    {
        $data = [
            'task' => AlarmTask::getSyncTasks(),
            'alarm_group' => AlarmGroup::getSyncGroups(),
            'template' => AlarmTemplate::getSyncTemplates(),
            'user' => User::getSyncUsers(),
        ];
        $this->cache->cover($data);
        $this->cache->isReady();
        // $this->stdoutLogger->info($this->name . '-dataSync');
    }
}
