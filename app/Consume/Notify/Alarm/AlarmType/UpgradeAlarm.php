<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class UpgradeAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_UPGRADE;

    protected function performReceiver(array $data = [])
    {
        if (isset($data['rule']['reuse_receiver']) && $data['rule']['reuse_receiver']) {
            $filterReceiver = $this->message->getTask()->getReceiver()->getDefaultChannels();
        } else {
            $filterReceiver = $data['rule']['receiver']->getDefaultChannels();
        }
        return $filterReceiver;
    }
}
