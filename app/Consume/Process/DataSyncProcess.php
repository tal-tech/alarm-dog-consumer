<?php

declare(strict_types=1);

namespace App\Consume\Process;

use App\Model\AlarmGroup;
use App\Model\AlarmTask;
use App\Model\AlarmTemplate;
use App\Model\User;
use App\Support\ProcessMessage;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Event;
use Swoole\Process;
use Swoole\Server;
use Swoole\Timer;
use Throwable;

/**
 * 数据同步进程.
 */
class DataSyncProcess extends AbstractProcess
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
    public $name = 'data-sync';

    /**
     * @var int
     */
    public $pipeType = SOCK_DGRAM;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * 文件缓存路径.
     *
     * @var string
     */
    protected $fileCachePath;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var StdoutLoggerInterface
     */
    protected $stdoutLogger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->logger = $this->container->get(LoggerFactory::class)->get('dataSync');
        $this->formatter = $this->container->get(FormatterInterface::class);
        $this->stdoutLogger = $this->container->get(StdoutLoggerInterface::class);
        $this->config = $this->container->get(ConfigInterface::class);

        $this->fileCachePath = $this->config->get('consumer.data_sync_file_cache_path');
        if (! is_dir(dirname($this->fileCachePath))) {
            mkdir(dirname($this->fileCachePath), 0777, true);
        }
    }

    public function handle(): void
    {
        // 立马同步一次
        if ($this->dataSync()) {
            // 通知event worker进程已经ready
            ProcessMessage::broadcastToEventWorkers(
                'dataSyncReady',
                [
                    'channel' => 'mysql',
                ]
            );
        } elseif ($data = $this->readFileCache()) {
            // 同步失败，但读取文件缓存成功，先使用文件缓存容灾
            ProcessMessage::broadcastToTaskWorkers('dataSync', $data);

            // 通知event worker进程已经ready
            ProcessMessage::broadcastToEventWorkers(
                'dataSyncReady',
                [
                    'channel' => 'file',
                ]
            );
        } else {
            // 同步失败且无文件缓存，输出错误，杀死主进程
            $this->stdoutLogger->error('data sync failed, mysql and file cache all not working');
            /** @var Server */
            $server = $this->container->get(Server::class);
            Process::kill($server->master_pid, SIGTERM);
        }

        $interval = (int) $this->config->get('consumer.data_sync_interval', 60000);
        Timer::tick(
            $interval,
            function () {
                $this->dataSync();
            }
        );

        Event::wait();
    }

    /**
     * 数据同步.
     *
     * @return bool 同步成功返回true，否则false
     */
    protected function dataSync()
    {
        try {
            $data = [
                'task' => AlarmTask::getSyncTasks(),
                'alarm_group' => AlarmGroup::getSyncGroups(),
                'template' => AlarmTemplate::getSyncTemplates(),
                'user' => User::getSyncUsers(),
            ];

            ProcessMessage::broadcastToTaskWorkers('dataSync', $data);

            // 刷新文件文件缓存
            $this->writeFileCache($data);

            return true;
        } catch (Throwable $e) {
            // 记录异常日志
            $this->logger->warning($this->formatter->format($e));

            return false;
        }
    }

    /**
     * 写入文件缓存.
     * @param mixed $data
     */
    protected function writeFileCache($data)
    {
        $cache = [
            'write_at' => time(),
            'data' => $data,
        ];
        file_put_contents($this->fileCachePath, json_encode($cache));
    }

    /**
     * 读取文件缓存.
     *
     * @return array|bool
     */
    protected function readFileCache()
    {
        if (! is_file($this->fileCachePath)) {
            $this->stdoutLogger->warning(sprintf('data sync file cache not exists in %s', $this->fileCachePath));
            return false;
        }
        $cache = file_get_contents($this->fileCachePath);
        $json = json_decode($cache, true);
        if (json_last_error() != JSON_ERROR_NONE || ! is_array($json) || ! isset($json['data'])) {
            $this->stdoutLogger->warning(sprintf('data sync file cache format is invalid in %s', $this->fileCachePath));
            return false;
        }

        $this->stdoutLogger->info(
            sprintf(
                'data sync using file cache successfully, please check mysql whether is working, because using file' .
                ' cache when mysql is exception.'
            )
        );
        $this->stdoutLogger->info(sprintf('file cache written time is %s', date('Y-m-d H:i:s', $json['write_at'])));

        return $json['data'];
    }
}
