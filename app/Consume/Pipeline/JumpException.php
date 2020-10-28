<?php

declare(strict_types=1);

namespace App\Consume\Pipeline;

use Exception;

/**
 * 用于在pipeline中终止逻辑直接切换工作流，并非真正异常类.
 */
class JumpException extends Exception
{
    /**
     * @var int
     */
    protected $pipelineStatus = Plant::PIPELINE_STATUS_NEXT;

    public function __construct(int $pipelineStatus = Plant::PIPELINE_STATUS_NEXT)
    {
        parent::__construct('JumpException');
        $this->pipelineStatus = $pipelineStatus;
    }

    /**
     * @return int
     */
    public function getPipelineStatus()
    {
        return $this->pipelineStatus;
    }
}
