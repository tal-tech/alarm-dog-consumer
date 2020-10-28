<?php

declare(strict_types=1);

namespace App\Consume\Compress\Method;

use App\Consume\Message\Message;

/**
 * 全量收敛.
 */
class Full extends MethodAbstract
{
    /**
     * 计算收敛指标.
     *
     * @return string
     */
    public function handle(Message $message): ?string
    {
        return sha1((string) $message->getSourcePayload()['taskid']);
    }
}
