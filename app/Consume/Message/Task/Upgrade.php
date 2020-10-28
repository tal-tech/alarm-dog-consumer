<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Support\SimpleCollection;

class Upgrade extends SimpleCollection
{
    public function __construct(?array $upgrade, Receiver $receiver, ?array $alarmGroups, ?array $users)
    {
        $strategies = [];
        $rules = [];
        foreach ($upgrade['strategies'] ?? [] as $strategy) {
            if ($strategy['reuse_receiver']) {
                $strategy['receiver'] = $receiver;
            } else {
                $strategy['receiver'] = new Receiver(
                    Receiver::parseAndMergeChannels($strategy['receiver'], $alarmGroups, $users)
                );
            }
            $strategies[$strategy['interval'] . '.' . $strategy['count']] = $strategy;
        }

        $this->elements = [
            'strategies' => $strategies,
        ];
    }

    /**
     * @return array
     */
    public function getStrategies()
    {
        return $this->elements['strategies'];
    }
}
