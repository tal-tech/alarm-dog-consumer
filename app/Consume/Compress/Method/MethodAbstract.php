<?php

declare(strict_types=1);

namespace App\Consume\Compress\Method;

use App\Consume\Message\Message;

abstract class MethodAbstract
{
    /**
     * 处理收敛策略.
     *
     * @return string
     */
    abstract public function handle(Message $message): ?string;
}
