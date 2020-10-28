<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class WorkFlowProcessingAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_REMIND_PROCESSING;

    protected function performReceiver(array $data = [])
    {
        return $this->workFlowPerformReceiver($data);
    }
}
