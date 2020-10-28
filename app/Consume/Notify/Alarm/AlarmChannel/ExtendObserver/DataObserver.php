<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver;

trait DataObserver
{
    private $data;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }
}
