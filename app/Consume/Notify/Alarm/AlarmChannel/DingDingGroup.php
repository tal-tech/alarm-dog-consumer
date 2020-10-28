<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Model\AlarmGroup;
use App\Model\AlarmTemplate;
use Dog\Noticer\Channel\DingGroup;
use Dog\Noticer\Exception\NoticeException;

class DingDingGroup extends AlarmChannelObserver
{
    protected $name = AlarmGroup::CHANNEL_DINGGROUP;

    protected $formatClass = [
        AlarmTemplate::FORMAT_TEXT => DingGroup\MsgType\Text::class,
        AlarmTemplate::FORMAT_MARKDOWN => DingGroup\MsgType\Markdown::class,
    ];

    /**
     * @param $msgType
     * @throws NoticeException
     */
    public function sendMsg($msgType)
    {
        $robots = $this->getReceiver();
        /** @var DingGroup $dinggroup */
        $dinggroup = make(DingGroup::class);
        $dinggroup->send($msgType, $robots, []);
    }
}
