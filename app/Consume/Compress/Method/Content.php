<?php

declare(strict_types=1);

namespace App\Consume\Compress\Method;

use App\Consume\Message\Message;

/**
 * 内容收敛.
 */
class Content extends MethodAbstract
{
    /**
     * 计算收敛指标.
     *
     * @return string
     */
    public function handle(Message $message): ?string
    {
        $payload = $message->getSourcePayload();

        // 一定要进行key排序，否则ctn的内容只是key的顺序不一致，会导致收敛失败
        $content = $payload['ctn'];
        $this->ksortRecursive($content);

        return sha1($payload['taskid'] . json_encode($content));
    }

    /**
     * 递归将数组根据key排序.
     */
    protected function ksortRecursive(array &$array)
    {
        ksort($array, SORT_STRING);
        foreach ($array as &$item) {
            if (is_array($item)) {
                $this->ksortRecursive($item);
            }
        }
    }
}
