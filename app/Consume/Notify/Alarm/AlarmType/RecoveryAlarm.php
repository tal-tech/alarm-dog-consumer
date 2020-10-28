<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class RecoveryAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_RECOVERY;
}
