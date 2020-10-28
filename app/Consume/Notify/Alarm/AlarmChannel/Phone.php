<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Model\AlarmGroup;
use Dog\Noticer\Channel\Phone as NoticePhone;
use Throwable;

class Phone extends AlarmChannelObserver
{
    public static $alarmPrefix = '哮天犬告警通知：';

    protected $name = AlarmGroup::CHANNEL_PHONE;

    public function sendMsg($msgType)
    {
        $users = $this->getReceiver();
        $receivers = $this->getReceivers($users);

        /** @var NoticePhone $phoneObj */
        $phoneObj = make(NoticePhone::class);
        // 将告警内容截断，避免超长发送失败
        $sendText = mb_substr($msgType, 0, 280);
        foreach ($receivers as $phone) {
            try {
                $phoneObj->send($sendText, $phone);
            } catch (Throwable $sendThrowable) {
            }
        }
    }
}
