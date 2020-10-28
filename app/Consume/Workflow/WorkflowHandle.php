<?php

declare(strict_types=1);

namespace App\Consume\Workflow;

use App\Consume\Notify\Notify;
use App\Consume\Process\AbstractHandle;
use App\Model\AlarmHistory;
use App\Model\AlarmTemplate as AlarmTpl;
use App\Model\AlarmWorkFlow;
use App\Model\AlarmWorkFlowDelay;
use App\Model\Workflow as ModelWorkflow;
use Monolog\Utils;
use Swoole\Coroutine;
use Throwable;

class WorkflowHandle extends AbstractHandle
{
    protected const NEXT_TIME = 5;

    protected $name = 'workflow';

    public function start()
    {
        while (true) {
            try {
                $delays = AlarmWorkFlowDelay::query()
                    ->where('trigger_time', '<', time())->orderBy('trigger_time')->get();
                if ($delays->isEmpty()) {
                    $this->stdoutLogger->info('workflow alarm empty');
                    Coroutine::sleep(self::NEXT_TIME);
                    continue;
                }
                $delays->each(
                    function ($delayObj, $key) {
                        $taskId = $delayObj->task_id;
                        $historyId = $delayObj->history_id;
                        $interval = $delayObj->interval;
                        $workflowId = $delayObj->workflow_id;
                        $status = $delayObj->status;
                        $remindKey = $interval . '.' . $status;
                        if (! $this->cache->getTask($taskId)) {
                            return;
                        }
                        $workflow = $this->cache->getTask($taskId)->getWorkflow();
                        if (is_null($workflow)) {
                            return;
                        }
                        $rule = $workflow->getReminds()[$remindKey];
                        $mode = $rule['mode'];

                        if (! $this->cache->getTask($taskId)->isRunning()) {
                            $this->logger->notice(
                                'work flow status is neq! '
                                . Utils::jsonEncode($delayObj)
                            );
                            return;
                        }

                        $workFLowObj = AlarmWorkFlow::query()->where('id', $workflowId)->first();
                        if (empty($workFLowObj)) {
                            $this->logger->error(
                                'work flow is not exists! '
                                . Utils::jsonEncode($delayObj)
                            );
                            return;
                        }

                        $historyObj = AlarmHistory::query()->where('id', $historyId)->first();
                        if (empty($historyObj)) {
                            $this->logger->error(
                                'history is not exists! '
                                . Utils::jsonEncode($delayObj)
                            );
                            return;
                        }

                        $workFlowData = empty($workFLowObj) ? [] : $workFLowObj->toArray();
                        $workFlowData['created_at'] = date('Y-m-d H:i:s', $workFlowData['created_at']);
                        $workFlowData['updated_at'] = date('Y-m-d H:i:s', $workFlowData['updated_at']);
                        switch ($workFLowObj->status) {
                            case ModelWorkflow::STATUS_PENDING:
                                //工作流状态匹配
                                if ($workFLowObj->status == $status) {
                                    // 投递给task处理
                                    $this->server->task(
                                        [
                                            Notify::MARK_SCENE => AlarmTpl::SCENE_REMIND_PENDING,
                                            Workflow::MARK_WORKFLOW_DATA => $workFlowData,
                                            'payload' => $historyObj->toArray(),
                                            'context' => $rule,
                                            'pipeline' => Workflow::class,
                                        ]
                                    );
                                }
                                break;
                            case ModelWorkflow::STATUS_PROCESSING:
                                if ($workFLowObj->status == $status) {
                                    // 投递给task处理
                                    $this->server->task(
                                        [
                                            Notify::MARK_SCENE => AlarmTpl::SCENE_REMIND_PROCESSING,
                                            Workflow::MARK_WORKFLOW_DATA => $workFlowData,
                                            'payload' => $historyObj->toArray(),
                                            'context' => $rule,
                                            'pipeline' => Workflow::class,
                                        ]
                                    );
                                }
                                break;
                        }
                        if ($mode == ModelWorkflow::CYCLE_MODE) {
                            AlarmWorkFlowDelay::query()
                                ->where('id', $delayObj->id)
                                ->update(
                                    [
                                        'trigger_time' => time() + $interval * 60,
                                        'updated_at' => time(),
                                    ]
                                );
                        } else {
                            AlarmWorkFlowDelay::query()
                                ->where('id', $delayObj->id)->delete();
                        }
                    }
                );
            } catch (Throwable $e) {
                $this->logger->error('work flow error! ' . $e->getMessage());
                $this->stdoutLogger->error('work flow error! ' . $e->getMessage());
                Coroutine::sleep(self::NEXT_TIME);
            }
        }
    }
}
