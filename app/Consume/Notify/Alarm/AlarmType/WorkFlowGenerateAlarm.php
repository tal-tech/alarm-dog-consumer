<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class WorkFlowGenerateAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_GENERATED;

    protected function performReceiver(array $data = [])
    {
        return $this->workFlowPerformReceiver($data);
    }
}
