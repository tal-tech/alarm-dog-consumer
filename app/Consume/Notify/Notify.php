<?php

declare(strict_types=1);

namespace App\Consume\Notify;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Notify\Alarm\Factory\AlarmFactory;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;

class Notify extends PipelineAbstract
{
    // 标记通知的场景
    public const MARK_SCENE = 'notifyScene';

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

    public const ALARM_LEVEL = [
        0 => '通知',
        1 => '警告',
        2 => '错误',
        3 => '紧急',
    ];

    /**
     * 工作流处理.
     *
     * @return int
     */
    public function handle(Message $message)
    {
        // 判断是否开启入库，并执行了入库
        if ($message->getTask()->isSaveDb() && ! $message->getProp(Message::IS_SAVE_DB)) {
            $this->saveDb($message);
        }
        // 告警被收敛，忽略告警
        if ($message->getProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY)) {
            return Plant::PIPELINE_STATUS_END;
        }

        // 发送普通通知
        AlarmFactory::getInstance()->setMessage($message)->notify();
        return Plant::PIPELINE_STATUS_END;
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'notify';
    }
}
