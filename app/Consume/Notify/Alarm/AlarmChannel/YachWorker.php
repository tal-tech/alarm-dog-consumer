<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Consume\Message\Task\User;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate;
use Dog\Noticer\Channel\YachWorker as DogYachWorker;

class YachWorker extends AlarmChannelObserver
{
    protected $name = AlarmGroup::CHANNEL_YACHWORKER;

    protected $formatClass = [
        AlarmTemplate::FORMAT_TEXT => DogYachWorker\MsgType\Text::class,
        AlarmTemplate::FORMAT_MARKDOWN => DogYachWorker\MsgType\Markdown::class,
    ];

    public function sendMsg($msgType)
    {
        $workCodes = array_map(
            function (User $user) {
                return sprintf('%06d', $user['uid']);
            },
            $this->getReceiver()
        );
        $options = [
            'user_type' => 'workcode',
            'userid_list' => implode('|', $workCodes),
        ];

        /** @var DogYachWorker $worker */
        $worker = make(DogYachWorker::class);
        $worker->send($msgType, $options);
    }
}
