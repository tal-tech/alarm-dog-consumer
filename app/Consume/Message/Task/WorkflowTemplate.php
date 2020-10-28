<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Support\SimpleCollection;

class WorkflowTemplate extends SimpleCollection
{
    public function getTemplate()
    {
        return $this->elements;
    }
}
