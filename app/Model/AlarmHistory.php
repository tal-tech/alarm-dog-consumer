<?php

declare(strict_types=1);

namespace App\Model;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;

class AlarmHistory extends Model
{
    public const NORMAL_ALARM = 1;

    public const RECOVER_ALARM = 2;

    public const IGNORE_ALARM = 3;

    public const ALARM_TYPE = 'type';

    public $timestamps = false;

    protected $table = 'alarm_history';

    protected $fillable = [
        'task_id',
        'uuid',
        'batch',
        'metric',
        'notice_time',
        'level',
        'ctn',
        'receiver',
        'type',
        'created_at',
    ];

    /**
     * @return AlarmHistory|bool
     */
    public static function customCreate(Message $message)
    {
        // 判断是否入库
        if (! $message->getTask()->isSaveDb()) {
            return false;
        }
        $payload = $message->getSourcePayload();
        // 判断是否已经入库
        if ($message->getProp(Message::IS_SAVE_DB)) {
            return $message->getAlarmHistory();
        }

        $receiver = '';
        if (isset($playload['receiver'])) {
            $receiver = is_array($payload['receiver']) ? json_encode($payload['receiver']) : $payload['receiver'];
        }

        $data = [
            'task_id' => $payload['taskid'],
            'uuid' => $payload['uuid'],
            'metric' => $message->getProp(Compress::MARK_COMPRESS_METRIC, ''),
            'batch' => $message->getProp(Compress::MARK_COMPRESS_BATCH, 0),
            'notice_time' => $payload['notice_time'] ?? time(),
            'level' => $payload['level'],
            'ctn' => is_array($payload['ctn']) ? json_encode($payload['ctn']) : $payload['ctn'],
            'receiver' => $receiver,
            'type' => $message->getProp(self::ALARM_TYPE),
            'created_at' => time(),
        ];
        $message->setProp(Message::IS_SAVE_DB, true);
        $model = AlarmHistory::create($data);
        $message->setAlarmHistory($model);
        return $model;
    }
}
