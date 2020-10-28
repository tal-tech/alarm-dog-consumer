<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm;

use App\Consume\Notify\Alarm\AlarmChannel\AlarmChannelObserver;

interface Observable
{
    public function attach(AlarmChannelObserver $observer);

    public function detach(AlarmChannelObserver $observer);

    public function notify();
}
