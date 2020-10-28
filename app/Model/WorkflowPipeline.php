<?php

declare(strict_types=1);

namespace App\Model;

class WorkflowPipeline extends Model
{
    public $timestamps = false;

    protected $table = 'workflow_pipeline';

    protected $fillable = ['task_id', 'workflow_id', 'status', 'remark', 'props', 'created_by', 'created_at'];
}
