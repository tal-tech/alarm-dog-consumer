<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver;

trait ReceiverObserver
{
    /**
     * @var array
     */
    private $receiver = [];

    private $channel = '';

    /**
     * @return mixed
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param mixed $channel
     */
    public function setChannel($channel): void
    {
        $this->channel = $channel;
    }

    /**
     * @return array
     */
    public function getReceiver()
    {
        return $this->receiver;
    }

    public function setReceiver(array $receiver): void
    {
        $this->receiver = $receiver;
    }
}
