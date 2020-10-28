<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Model\AlarmGroup;
use Dog\Noticer\Channel\Sms as NoticeSms;

class Sms extends AlarmChannelObserver
{
    public static $alarmPrefix = '[哮天犬]';

    protected $name = AlarmGroup::CHANNEL_SMS;

    public function sendMsg($msgType)
    {
        $users = $this->getReceiver();
        $receivers = $this->getReceivers($users);
        $tplId = config('xtq_notice.sms_tplid');
        $param = [
            // 将告警内容截断，避免超长发送失败
            mb_substr($msgType, 0, 300),
        ];
        if (! empty($receivers)) {
            /** @var NoticeSms $sms */
            $sms = make(NoticeSms::class);
            $sms->send($tplId, $param, $receivers);
        }
    }
}
