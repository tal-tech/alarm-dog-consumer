<?php

declare(strict_types=1);

namespace App\Consume\Compress;

use App\Consume\Notify\Notify;
use App\Consume\Process\AbstractHandle;
use App\Consume\Workflow\Workflow;
use App\Model\AlarmDelayCompresss;
use App\Model\AlarmHistory;
use Swoole\Coroutine;
use Throwable;

class DelayHandle extends AbstractHandle
{
    protected $name = 'compress-delay';

    public function start()
    {
        while (true) {
            try {
                $collect = AlarmDelayCompresss::query()
                    ->where('trigger_time', '<', time())
                    ->get()->toArray();
                if (empty($collect)) {
                    $this->stdoutLogger->info('compress delay empty');
                    Coroutine::sleep(5);
                    continue;
                }
                foreach ($collect as $key => $value) {
                    $historyObj = AlarmHistory::query()
                        ->where('id', $value['history_id'])->first();
                    if (! is_null($historyObj)) {
                        $historyArr = $historyObj->toArray();
                        $this->server->task(
                            [
                                Notify::MARK_SCENE => Notify::SCENE_COMPRESSED,
                                'payload' => $historyArr,
                                'pipeline' => Workflow::class,
                            ]
                        );
                    }
                    AlarmDelayCompresss::query()
                        ->where('id', $value['id'])->delete();
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage() . '---' . $e->getTraceAsString();
                $this->stdoutLogger->error($msg);
                $this->logger->error($msg);
            }
        }
    }
}
