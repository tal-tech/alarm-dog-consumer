<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Model\AlarmTemplate;

class NotCompressAlarm extends AbsAlarmType
{
    protected $name = AlarmTemplate::SCENE_NOT_COMPRESS;
}
