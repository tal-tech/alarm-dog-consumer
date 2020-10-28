<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Support\SimpleCollection;

/**
 * 工作流，此处仅缓存状态为待处理且只包含部分字段的数据，并不是全部数据.
 */
class Workflow extends SimpleCollection
{
    /**
     * @var WorkflowTemplate
     */
    protected $workflowTemplate;

    public function __construct(?array $workflow, Receiver $receiver, ?array $alarmGroups, ?array $users, WorkflowTemplate $workflowTemplate)
    {
        $reminds = [];
        foreach ($workflow['reminds'] ?? [] as $remind) {
            if ($remind['reuse_receiver']) {
                $remind['receiver'] = $receiver;
            } else {
                $remind['receiver'] = new Receiver(
                    Receiver::parseAndMergeChannels($remind['receiver'], $alarmGroups, $users)
                );
            }
            $reminds[$remind['interval'] . '.' . $remind['status']] = $remind;
        }

        $this->elements = [
            'reminds' => $reminds,
        ];
        $this->workflowTemplate = $workflowTemplate;
    }

    public function getReminds()
    {
        return $this->elements['reminds'];
    }

    public function getTemplate()
    {
        return $this->workflowTemplate->getTemplate();
    }
}
