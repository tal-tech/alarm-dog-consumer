<?php

declare(strict_types=1);

namespace App\Model;

class Workflow extends Model
{
    /**
     * 工作流状态
     */
    // 待处理
    public const STATUS_PENDING = 0;

    // 处理中
    public const STATUS_PROCESSING = 1;

    // 处理完成
    public const STATUS_PROCESSED = 2;

    // 关闭
    public const STATUS_CLOSED = 9;

    /**
     * 通知模式.
     */
    // 通知一次
    public const ONCE_MODE = 'once';

    // 循环通知
    public const CYCLE_MODE = 'cycle';

    /**
     * 通知模板
     */
    // 生成告警任务
    public const SCENE_GENERATED = 'generated';

    // 认领
    public const SCENE_CLAIM = 'claim';

    // 指派
    public const SCENE_ASSIGN = 'assign';

    // 处理完成
    public const SCENE_PROCESSED = 'processed';

    // 重新激活
    public const SCENE_REACTIVE = 'reactive';

    // 关闭
    public const SCENE_CLOSE = 'close';

    // 提醒-待处理
    public const SCENE_REMIND_PENDING = 'remind_pending';

    // 提醒-处理中
    public const SCENE_REMIND_PROCESSING = 'remind_processing';

    public $timestamps = false;

    protected $table = 'workflow';

    protected $fillable = ['task_id', 'metric', 'history_id', 'status', 'created_at', 'updated_at'];
}
