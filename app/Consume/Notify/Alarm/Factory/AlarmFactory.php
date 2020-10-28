<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\Factory;

use App\Consume\Notify\Alarm\AlarmObservable;

class AlarmFactory
{
    /**
     * @return AlarmObservable
     */
    public static function getInstance()
    {
        return make(AlarmObservable::class);
    }
}
