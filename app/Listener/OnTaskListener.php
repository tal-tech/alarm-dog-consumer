<?php

declare(strict_types=1);

namespace App\Listener;

use App\Consume\Cache\CacheInterface;
use App\Consume\Compress\Compress;
use App\Consume\Driver\DriverAbstract;
use App\Consume\Message\Message;
use App\Consume\Notify\Notify;
use App\Consume\Pipeline\Plant;
use App\Consume\Workflow\Workflow;
use App\Model\AlarmHistory;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Framework\Event\OnTask;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Server\Task;
use Throwable;

/**
 * @Listener
 */
class OnTaskListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var Plant
     */
    protected $plant;

    /**
     * @var mixed|StdoutLoggerInterface
     */
    protected $stdoutLogger;

    /**
     * @var FormatterInterface
     */
    private $formatter;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerFactory::class)->get('worker');
        $this->stdoutLogger = $container->get(StdoutLoggerInterface::class);
        $this->formatter = $container->get(FormatterInterface::class);
        $this->cache = $container->get(CacheInterface::class);
        $this->plant = $container->get(Plant::class);
    }

    public function listen(): array
    {
        return [
            OnTask::class,
        ];
    }

    /**
     * @param OnTask $event
     */
    public function process(object $event)
    {
        $sub = false;
        try {
            // 告警升级、延迟告警、工作流通知
            $data = $event->task->data;
            if ($this->notify($data)) {
                return;
            }

            $sub = true;
            if (! ($task = $this->cache->getTask($data['payload']['taskid']))) {
                // 任务不存在，数据不符合规范，直接ack
                // TODO 记录日志
                $this->logger->warning('taskid 未找到', $event->task->data);
                $this->finish($event->task);
                return;
            }

            // 任务存在，进入工厂车间
            $message = new Message($event->task->data['payload'], $task);
            if ($this->plant->consume($message)) {
                $this->finish($event->task);
            }
        } catch (Throwable $e) {
            // TODO 记录日志
            $this->logger->error($this->formatter->format($e));
            $this->stdoutLogger->error($e->getMessage());
        } finally {
            if ($sub) {
                $event->server->consumingCount->sub(1);
            }
        }
    }

    protected function finish(Task $task)
    {
        /** @var DriverAbstract $driver */
        $driver = $task->data['driver'];
        $message = $driver::buildAckFinish($task->data);
        $task->finish($message);
    }

    protected function notify($data)
    {
        if (! isset($data[Notify::MARK_SCENE])) {
            return false;
        }
        // dump($data);
        if (! ($task = $this->cache->getTask($data['payload']['task_id']))) {
            // 任务不存在，数据不符合规范，直接ack
            $this->logger->warning('taskid 未找到', $data);
            return true;
        }
        $message = new Message($data['payload'], $task);
        // 设置入库标志
        $message->setProp(Message::IS_SAVE_DB, true);
        $message->setAlarmHistory(AlarmHistory::find($data['payload']['id']));
        // 设置下一步工作流
        $message->setJump($this->container->get($data['pipeline'] ?? Notify::class));
        // 设置通知场景
        $message->setProp(Notify::MARK_SCENE, $data[Notify::MARK_SCENE]);
        // 设置收敛信息
        $message->setProp(Compress::MARK_COMPRESS_METRIC, $data['payload']['metric'] ?: null);
        $message->setProp(Compress::MARK_COMPRESS_BATCH, $data['payload']['batch'] ?: null);
        // 设置workflow数据
        $message->setProp(Workflow::MARK_WORKFLOW_DATA, $data[Workflow::MARK_WORKFLOW_DATA] ?? null);
        $message->setProp('context', $data['context'] ?? null);

        // 流水线处理消息
        if ($this->plant->consume($message, Plant::PIPELINE_STATUS_NEXT_FROM_JUMP)) {
            return true;
        }
        return true;
    }
}
