<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver;

use App\Consume\Message\Message;

trait MessageObserver
{
    /**
     * @var Message
     */
    private $message;

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): void
    {
        $this->message = $message;
    }

    public function getTask()
    {
        return $this->message->getTask();
    }
}
