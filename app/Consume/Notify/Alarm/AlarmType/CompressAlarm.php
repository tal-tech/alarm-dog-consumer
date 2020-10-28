<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class CompressAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_COMPRESSED;
}
