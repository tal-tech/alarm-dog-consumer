<?php

declare(strict_types=1);

namespace App\Consume\Workflow;

use App\Consume\Compress\Compress;
use App\Consume\Message\Message;
use App\Consume\Notify\Notify;
use App\Consume\Pipeline\PipelineAbstract;
use App\Consume\Pipeline\Plant;
use App\Model\AlarmHistory;
use App\Model\AlarmTemplate as AlarmTpl;
use App\Model\AlarmWorkFlow;
use App\Model\AlarmWorkFlowDelay;
use App\Model\AlarmWorkFlowPipeline;
use App\Model\Workflow as ModelWorkflow;
use Throwable;

class Workflow extends PipelineAbstract
{
    // 工作流通知
    public const MARK_WORKFLOW_NOTIFY = 'workflowNotify';

    public const MARK_WORKFLOW_DATA = 'workflowData';

    /**
     * 工作流处理.
     *
     * @return int
     */
    public function handle(Message $message)
    {
        // 未开启或者未收敛，无法使用工作流，顺序执行，后续优化，允许在未使用收敛的时候使用工作流
        if (! $message->getTask()->isEnableWorkflow() || ! $message->getProp(Compress::MARK_COMPRESS_METRIC)) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        // 消息被忽略，不进行工作流处理
        if ($message->getProp(Compress::MARK_COMPRESS_IGNORE_NOTIFY)) {
            return Plant::PIPELINE_STATUS_NEXT;
        }

        return $this->dispatch($message);
    }

    /**
     * 工作流名称.
     */
    public function getName(): string
    {
        return 'workflow';
    }

    protected function dispatch(Message $message)
    {
        try {
            $scene = $message->getProp(Notify::MARK_SCENE);
            if (in_array($scene, [AlarmTpl::SCENE_REMIND_PENDING, AlarmTpl::SCENE_REMIND_PROCESSING])) {
                $this->saveWorkflowPipeline($message);
            } else {
                // 写入到数据库
                $this->saveWorkflow($message);
                // 发送工作流通知
                $message->setProp(self::MARK_WORKFLOW_NOTIFY, AlarmTpl::SCENE_GENERATED);
                // 是否设置提醒
                $this->workflowByReminds($message);
            }
        } catch (Throwable $e) {
            $this->logger->error('save work flow error' . $e->getMessage() . '----trace:' . $e->getTraceAsString());
        }
        return Plant::PIPELINE_STATUS_NEXT;
    }

    /**
     * 保存工作流数据.
     */
    protected function saveWorkflow(Message $message)
    {
        try {
            /** @var AlarmHistory $history */
            $historyObj = $message->getAlarmHistory();
            $historyId = $historyObj['id'];
            $metric = $message->getProp(Compress::MARK_COMPRESS_METRIC);
            $taskId = $message->getTask()['id'];
            $data = [
                'task_id' => $taskId,
                'metric' => $metric,
                'history_id' => $historyId,
                'status' => 0,
                'created_at' => time(),
                'updated_at' => time(),
            ];
            $workFlow = AlarmWorkFlow::create($data);
            $data['id'] = $workFlow['id'];
            $data['created_at'] = date('Y-m-d H:i:s', $data['created_at']);
            $data['updated_at'] = date('Y-m-d H:i:s', $data['updated_at']);

            // 设置工作流数据
            $message->setProp(self::MARK_WORKFLOW_DATA, $data);
        } catch (Throwable $e) {
            $this->logger->error('save workflow error' . $e->getMessage() . '----trace:' . $e->getTraceAsString());
        }
    }

    /**
     * 保存工作流Pipeline数据.
     */
    protected function saveWorkflowPipeline(Message $message)
    {
        try {
            $payload = $message->getSourcePayload();
            $workflow = $message->getProp(Workflow::MARK_WORKFLOW_DATA);
            $context = $message->getProp('context');
            $remark = [
                'remind' => [
                    'interval' => $context['interval'],
                    'status' => $context['status'],
                ],
            ];
            $data = [
                'task_id' => $payload['task_id'],
                'workflow_id' => $workflow['id'],
                'status' => 3,
                'remark' => '提醒',
                'props' => json_encode($remark),
                'created_by' => 0,
                'created_at' => time(),
            ];
            AlarmWorkFlowPipeline::create($data);
        } catch (Throwable $e) {
            $this->logger->error(
                'save workflow pipeline error' . $e->getMessage() . '----trace:' . $e->getTraceAsString()
            );
        }
    }

    /**
     * 设置提醒.
     */
    protected function workflowByReminds(Message $message)
    {
        try {
            $reminds = $message->getTask()->getWorkflow()->getReminds();
            foreach ($reminds as $remind) {
                if ($remind['status'] == ModelWorkflow::STATUS_PENDING) {
                    //to pipeline
                    $delayData = [
                        'task_id' => $message->getTask()['id'],
                        'history_id' => $message->getAlarmHistory()['id'],
                        'interval' => $remind['interval'],
                        'workflow_id' => $message->getProp(self::MARK_WORKFLOW_DATA)['id'],
                        'status' => ModelWorkflow::STATUS_PENDING,
                        'trigger_time' => (int) ($remind['interval'] * 60 + time()),
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                    AlarmWorkFlowDelay::create($delayData);
                }
            }
        } catch (Throwable $e) {
            $this->logger->error(
                'save workflow reminds error' . $e->getMessage() . '----trace:' . $e->getTraceAsString()
            );
        }
    }
}
