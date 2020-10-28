<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Consume\Message\Task\User;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate;
use Dog\Noticer\Channel\DingWorker;
use Dog\Noticer\Exception\NoticeException;
use Dog\Noticer\Exception\ReachLimitException;

class DingDingWorker extends AlarmChannelObserver
{
    protected $name = AlarmGroup::CHANNEL_DINGWORKER;

    protected $formatClass = [
        AlarmTemplate::FORMAT_TEXT => DingWorker\MsgType\Text::class,
        AlarmTemplate::FORMAT_MARKDOWN => DingWorker\MsgType\Markdown::class,
    ];

    /**
     * @param $msgType
     * @throws NoticeException
     * @throws ReachLimitException
     */
    public function sendMsg($msgType)
    {
        $workCodes = array_map(
            function (User $user) {
                return sprintf('%06d', $user['uid']);
            },
            $this->getReceiver()
        );

        $options = [
            'workcodes' => implode('|', $workCodes),
        ];

        /** @var DingWorker $dingWorker */
        $dingWorker = make(DingWorker::class);
        $dingWorker->send($msgType, $options);
    }
}
