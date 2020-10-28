<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Model\AlarmGroup;
use App\Model\AlarmTemplate as AlarmTpl;
use Dog\Noticer\Channel\Email as NoticeEmail;

class Email extends AlarmChannelObserver
{
    protected $name = AlarmGroup::CHANNEL_EMAIL;

    public function sendMsg($msgType)
    {
        $template = $this->getTask()->getTemplate()->getTemplate();
        $format = $template[$this->getScene()][$this->getChannel()]['format'];

        $users = $this->getReceiver();
        $emails = [];
        foreach ($users as $user) {
            $email = $user['email'];
            $emails[$email] = explode('@', $email)[0];
        }
        /** @var NoticeEmail $mail */
        $mail = make(NoticeEmail::class);
        $mailObj = $mail->to($emails)
            ->subject($this->getSubject());
        if ($format == AlarmTpl::FORMAT_TEXT) {
            $mailObj->text($msgType);
        } elseif ($format == AlarmTpl::FORMAT_HTML) {
            $mailObj->html($msgType);
        }
        $mailObj->send();
    }

    protected function init()
    {
        $admin_url = config('app.admin_url');
        self::$alarmSuffix = '
    
本邮件由哮天犬监控告警平台(' . $admin_url . ')发出，请勿回复。';
        self::$alarmHtmlSuffix = '<p><br></p><p style="font-size: 12px; color: #aaa">本邮件由<a href="' . $admin_url . '" target="_blank" style="color: #3b73af">哮天犬监控告警平台</a>发出，请勿回复。</p>';
    }
}
