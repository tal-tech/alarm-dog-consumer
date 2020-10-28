<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver;

trait SceneObserver
{
    private $scene;

    /**
     * @return mixed
     */
    public function getScene()
    {
        return $this->scene;
    }

    /**
     * @param $scene
     */
    public function setScene($scene): void
    {
        $this->scene = $scene;
    }
}
