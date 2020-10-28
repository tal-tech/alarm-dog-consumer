<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Model\AlarmGroup;
use App\Model\AlarmTemplate;
use Dog\Noticer\Channel\YachGroup as DogYachGroup;
use Dog\Noticer\Exception\NoticeException;

class YachGroup extends AlarmChannelObserver
{
    protected $name = AlarmGroup::CHANNEL_YACHGROUP;

    protected $formatClass = [
        AlarmTemplate::FORMAT_TEXT => DogYachGroup\MsgType\Text::class,
        AlarmTemplate::FORMAT_MARKDOWN => DogYachGroup\MsgType\Markdown::class,
    ];

    /**
     * @param $msgType
     * @throws NoticeException
     */
    public function sendMsg($msgType)
    {
        $robots = $this->getReceiver();
        /** @var DogYachGroup $yach */
        $yach = make(DogYachGroup::class);
        $yach->send($msgType, $robots);
    }
}
