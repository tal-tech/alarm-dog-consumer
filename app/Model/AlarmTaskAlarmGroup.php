<?php

declare(strict_types=1);

namespace App\Model;

class AlarmTaskAlarmGroup extends Model
{
    // 告警通知人
    public const TYPE_RECEIVER = 1;

    // 告警升级
    public const TYPE_UPGRADE = 2;

    // 告警工作流
    public const TYPE_WORKFLOW = 3;

    public $timestamps = false;

    protected $table = 'alarm_task_alarm_group';

    protected $fillable = ['task_id', 'group_id', 'type'];
}
