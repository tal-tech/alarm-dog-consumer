<?php

declare(strict_types=1);

namespace App\Model;

class AlarmGroup extends Model
{
    // 短信通知
    public const CHANNEL_SMS = 'sms';

    // 电话通知
    public const CHANNEL_PHONE = 'phone';

    // 邮件通知
    public const CHANNEL_EMAIL = 'email';

    // 钉钉工作通知
    public const CHANNEL_DINGWORKER = 'dingworker';

    // 微信通知
    public const CHANNEL_WECHAT = 'wechat';

    // 钉钉群通知
    public const CHANNEL_DINGGROUP = 'dinggroup';

    // Yach群通知
    public const CHANNEL_YACHGROUP = 'yachgroup';

    // Yach工作通知
    public const CHANNEL_YACHWORKER = 'yachworker';

    // WebHook通知
    public const CHANNEL_WEBHOOK = 'webhook';

    // 分级告警：懒得模式
    public const RECV_DISPATCH_MODE_LAZY = 1;

    // 分级告警：非懒惰模式
    public const RECV_DISPATCH_MODE_UNLAZY = 2;

    public $timestamps = false;

    /**
     * 所有通知渠道.
     */
    public static $channels = [
        self::CHANNEL_SMS,
        self::CHANNEL_EMAIL,
        self::CHANNEL_PHONE,
        self::CHANNEL_DINGWORKER,
        self::CHANNEL_DINGGROUP,
        self::CHANNEL_YACHGROUP,
        self::CHANNEL_YACHWORKER,
        self::CHANNEL_WEBHOOK,
    ];

    protected $table = 'alarm_group';

    protected $fillable = ['name', 'pinyin', 'remark', 'receiver', 'created_by', 'created_at', 'updated_at'];

    protected $casts = [
        'receiver' => 'array',
    ];

    /**
     * 查询用于同步的告警通知组.
     */
    public static function getSyncGroups()
    {
        return AlarmGroup::select('id', 'name', 'receiver')->get()->keyBy('id')->toArray();
    }
}
