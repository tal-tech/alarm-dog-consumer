<?php

declare(strict_types=1);

return [
    \App\Consume\Process\DataSyncProcess::class,
    \App\Consume\Process\RedisMsgAckProcess::class,
    \App\Consume\Process\AlarmUpgradeProcess::class,
    \App\Consume\Process\WorkflowProcess::class,
    \App\Consume\Process\DelayProcess::class,
];
