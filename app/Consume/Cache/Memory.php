<?php

declare(strict_types=1);

namespace App\Consume\Cache;

use App\Consume\Message\Task;
use App\Consume\Message\Task\Compress;
use App\Consume\Message\Task\Filter;
use App\Consume\Message\Task\Receiver;
use App\Consume\Message\Task\Recovery;
use App\Consume\Message\Task\Template;
use App\Consume\Message\Task\Upgrade;
use App\Consume\Message\Task\User;
use App\Consume\Message\Task\Workflow;
use App\Consume\Message\Task\WorkflowTemplate;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Utils\Arr;
use Psr\Container\ContainerInterface;

/**
 * 内存缓存.
 */
class Memory implements CacheInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * key为uid，value为User.
     *
     * @var User[]
     */
    protected $users = [];

    /**
     * key为taskid，value为Task.
     *
     * @var Task[]
     */
    protected $tasks = [];

    /**
     * @var WorkflowTemplate
     */
    protected $workflowTemplate;

    /**
     * @var Template
     */
    protected $defaultTemplate;

    /**
     * @var bool
     */
    protected $isReady = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);

        // 默认模板
        $this->defaultTemplate = new Template(
            0,
            'default',
            Template::formatTemplate($this->config->get('dog-templates.tasks')),
            AlarmTemplate::TYPE_DEFAULT
        );

        // 工作流模板
        $this->workflowTemplate = new WorkflowTemplate(Template::formatTemplate($this->config->get('workflow-templates')));
    }

    /**
     * 是否已经ready.
     */
    public function isReady(): bool
    {
        return $this->isReady;
    }

    /**
     * 获取任务
     *
     * @param int $taskId
     */
    public function getTask($taskId): ?Task
    {
        return $this->tasks[$taskId] ?? null;
    }

    /**
     * 获取所有任务
     *
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * 获取用户.
     *
     * @param int $uid
     */
    public function getUser($uid): ?User
    {
        return $this->users[$uid] ?? null;
    }

    /**
     * 获取所有用户.
     *
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    /**
     * 获取默认模板
     */
    public function getDefaultTemplate(): Template
    {
        return $this->defaultTemplate;
    }

    /**
     * 获取工作流模板
     */
    public function getWorkflowTemplate(): WorkflowTemplate
    {
        return $this->workflowTemplate;
    }

    /**
     * 覆盖.
     *
     * @param array $data
     */
    public function cover($data): void
    {
        // 解析用户信息
        $users = [];
        foreach ($data['user'] as $uid => $item) {
            $users[$uid] = new User($item);
        }
        $this->users = $users;

        // 解析预定义模板
        $templates = [];
        foreach ($data['template'] as $item) {
            $templates[$item['id']] = new Template(
                $item['id'],
                $item['name'],
                Template::mergeTemplate($item['template']),
                AlarmTemplate::TYPE_PREDEFINED
            );
        }

        // 解析告警任务
        $tasks = [];
        foreach ($data['task'] as $item) {
            $config = $item['config'];
            $task = Arr::only(
                $item,
                [
                    'id',
                    'name',
                    'flag_save_db',
                    'enable_workflow',
                    'enable_filter',
                    'enable_compress',
                    'enable_upgrade',
                    'enable_recovery',
                    'status',
                ]
            );
            $task['receiver'] = new Receiver(
                Receiver::parseAndMergeChannels($config['receiver'], $data['alarm_group'], $users),
                ! empty($config['receiver']['dispatch']),
                Receiver::parseDispatchAndMergeChannels(
                    $config['receiver']['dispatch'] ?? [],
                    $data['alarm_group'],
                    $users
                ),
                $config['receiver']['mode'] ?? AlarmGroup::RECV_DISPATCH_MODE_LAZY
            );
            $task['workflow'] = new Workflow($config['workflow'], $task['receiver'], $data['alarm_group'], $users, $this->getWorkflowTemplate());
            $task['compress'] = new Compress($config['compress']);
            $task['filter'] = new Filter($config['filter']);
            $task['recovery'] = new Recovery($config['recovery']);
            $task['upgrade'] = new Upgrade($config['upgrade'], $task['receiver'], $data['alarm_group'], $users);
            $task['template'] = $config['alarm_template_id'] && isset($templates[$config['alarm_template_id']]) ?
                $templates[$config['alarm_template_id']] : (
                    empty($config['alarm_template']) ? $this->getDefaultTemplate() : new Template(
                        0,
                        'custom#' . $task['id'],
                        Template::mergeTemplate($config['alarm_template']),
                        AlarmTemplate::TYPE_CUSTOM
                    )
                );

            $tasks[$task['id']] = new Task($task);
        }
        $this->tasks = $tasks;

        // 设置ready状态
        $this->isReady = true;
    }
}
