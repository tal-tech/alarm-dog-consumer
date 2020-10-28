<?php

declare(strict_types=1);

namespace App\Model;

class AlarmTemplate extends Model
{
    /**
     * 模板类型.
     */
    // 默认模板
    public const TYPE_DEFAULT = 0;

    // 预定义模板
    public const TYPE_PREDEFINED = 1;

    // 自定义模板
    public const TYPE_CUSTOM = 2;

    /**
     * 模板场景.
     */
    // 告警被收敛
    public const SCENE_COMPRESSED = 'compressed';

    // 告警未收敛
    public const SCENE_NOT_COMPRESS = 'not_compress';

    // 告警升级
    public const SCENE_UPGRADE = 'upgrade';

    // 告警自动恢复
    public const SCENE_RECOVERY = 'recovery';

    // 工作流
    // public const SCENE_WORKFLOW = "workflow";

    /**
     * 模板格式类型.
     */
    public const FORMAT_TEXT = 1;

    public const FORMAT_MARKDOWN = 2;

    public const FORMAT_HTML = 3;

    public const FORMAT_ACTIONCARD = 4;

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

    public $casts = [
        'template' => 'array',
    ];

    /**
     * 告警模板场景.
     */
    public static $scenes = [
        self::SCENE_COMPRESSED => '告警被收敛',
        self::SCENE_NOT_COMPRESS => '告警未收敛',
        self::SCENE_UPGRADE => '告警升级',
        self::SCENE_RECOVERY => '告警自动恢复',
    ];

    /**
     * 告警模板可用渠道.
     */
    public static $channels = [
        AlarmGroup::CHANNEL_SMS,
        AlarmGroup::CHANNEL_EMAIL,
        AlarmGroup::CHANNEL_PHONE,
        AlarmGroup::CHANNEL_DINGWORKER,
        AlarmGroup::CHANNEL_DINGGROUP,
        AlarmGroup::CHANNEL_YACHWORKER,
        AlarmGroup::CHANNEL_YACHGROUP,
    ];

    protected $table = 'alarm_template';

    protected $fillable = ['name', 'pinyin', 'remark', 'template', 'created_by', 'created_at', 'updated_at'];

    /**
     * 查询用于同步的告警模板
     */
    public static function getSyncTemplates()
    {
        return AlarmTemplate::select('id', 'name', 'template')->get()->keyBy('id')->toArray();
    }
}
