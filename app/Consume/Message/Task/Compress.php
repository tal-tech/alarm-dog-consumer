<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Support\SimpleCollection;

class Compress extends SimpleCollection
{
    public const COMPRESS_METHOD = [
        1 => '条件',
        2 => '智能',
        3 => '内容',
        4 => '全量',
    ];

    public const COMPRESS_TYPE = [
        1 => '周期',
        2 => '延时',
        3 => '周期次数',
        4 => '次数周期',
        5 => '次数',
    ];

    public function getMethod()
    {
        return $this->elements['method'];
    }

    public function getStratrgy()
    {
        return $this->elements['strategy'];
    }
}
