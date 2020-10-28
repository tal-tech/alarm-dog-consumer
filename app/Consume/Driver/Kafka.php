<?php

declare(strict_types=1);

namespace App\Consume\Driver;

class Kafka extends DriverAbstract
{
    public function consume(): void
    {
    }

    public function getName(): string
    {
        return 'kafka';
    }

    /**
     * @param mixed $data
     */
    public function ack($data): void
    {
    }

    /**
     * 创建ack finish消息体.
     *
     * @param mixed $data
     */
    public static function buildAckFinish($data): string
    {
        return '';
    }

    /**
     * @return mixed
     */
    public function parseAckFinish(string $data)
    {
        return null;
    }

    /**
     * 是否仍然有未ack的数据.
     */
    public function hasAck(): bool
    {
        return false;
    }
}
