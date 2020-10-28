<?php

declare(strict_types=1);

namespace App\Consume\Cache;

use App\Consume\Message\Task;
use App\Consume\Message\Task\Template;
use App\Consume\Message\Task\User;
use App\Consume\Message\Task\WorkflowTemplate;

interface CacheInterface
{
    /**
     * 是否已经ready.
     */
    public function isReady(): bool;

    /**
     * 获取任务
     *
     * @param int $taskId
     */
    public function getTask($taskId): ?Task;

    /**
     * 获取所有任务
     *
     * @return Task[]
     */
    public function getTasks(): array;

    /**
     * 获取用户.
     *
     * @param int $uid
     */
    public function getUser($uid): ?User;

    /**
     * 获取所有用户.
     *
     * @return User[]
     */
    public function getUsers(): array;

    /**
     * 获取默认模板
     */
    public function getDefaultTemplate(): Template;

    /**
     * 获取工作流模板
     */
    public function getWorkflowTemplate(): WorkflowTemplate;

    /**
     * 覆盖.
     *
     * @param array $data
     */
    public function cover($data): void;
}
