<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver;

trait SubjectObserver
{
    private $subject;

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param $subject
     */
    public function setSubject($subject): void
    {
        $this->subject = $subject;
    }
}
