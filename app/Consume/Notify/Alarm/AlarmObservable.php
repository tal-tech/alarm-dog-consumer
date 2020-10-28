<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm;

use App\Consume\Message\Message;
use App\Consume\Notify\Alarm\AlarmChannel\AlarmChannelObserver;
use App\Consume\Notify\Alarm\AlarmChannel\DingDingGroup;
use App\Consume\Notify\Alarm\AlarmChannel\DingDingWorker;
use App\Consume\Notify\Alarm\AlarmChannel\Email;
use App\Consume\Notify\Alarm\AlarmChannel\Phone;
use App\Consume\Notify\Alarm\AlarmChannel\Sms;
use App\Consume\Notify\Alarm\AlarmChannel\WebHook;
use App\Consume\Notify\Alarm\AlarmChannel\YachGroup;
use App\Consume\Notify\Alarm\AlarmChannel\YachWorker;
use App\Consume\Notify\Alarm\AlarmType\AbsAlarmType;
use App\Consume\Notify\Alarm\AlarmType\CompressAlarm;
use App\Consume\Notify\Alarm\AlarmType\NotCompressAlarm;
use App\Consume\Notify\Alarm\AlarmType\RecoveryAlarm;
use App\Consume\Notify\Alarm\AlarmType\UpgradeAlarm;
use App\Consume\Notify\Alarm\AlarmType\WorkFlowGenerateAlarm;
use App\Consume\Notify\Alarm\AlarmType\WorkFlowPendingAlarm;
use App\Consume\Notify\Alarm\AlarmType\WorkFlowProcessingAlarm;
use App\Consume\Notify\Notify;
use App\Consume\Workflow\Workflow;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate as AlarmTpl;
use App\Support\ConditionArr;

class AlarmObservable implements Observable
{
    /**
     * 告警渠道列表.
     */
    private const OBSERVER_CLASS = [
        AlarmGroup::CHANNEL_DINGGROUP => DingDingGroup::class,
        AlarmGroup::CHANNEL_DINGWORKER => DingDingWorker::class,
        AlarmGroup::CHANNEL_YACHGROUP => YachGroup::class,
        AlarmGroup::CHANNEL_YACHWORKER => YachWorker::class,
        AlarmGroup::CHANNEL_EMAIL => Email::class,
        AlarmGroup::CHANNEL_PHONE => Phone::class,
        AlarmGroup::CHANNEL_SMS => Sms::class,
        AlarmGroup::CHANNEL_WEBHOOK => WebHook::class,
    ];

    private const SCENE_CLASS = [
        AlarmTpl::SCENE_COMPRESSED => CompressAlarm::class,
        AlarmTpl::SCENE_NOT_COMPRESS => NotCompressAlarm::class,
        AlarmTpl::SCENE_UPGRADE => UpgradeAlarm::class,
        AlarmTpl::SCENE_RECOVERY => RecoveryAlarm::class,
        AlarmTpl::SCENE_GENERATED => WorkFlowGenerateAlarm::class,
        AlarmTpl::SCENE_REMIND_PENDING => WorkFlowPendingAlarm::class,
        AlarmTpl::SCENE_REMIND_PROCESSING => WorkFlowProcessingAlarm::class,
    ];

    /**
     * @var AbsAlarmType
     */
    public $alarmType;

    /**
     * @var Message
     */
    protected $message;

    /**
     * @var AlarmChannelObserver[]
     */
    private $observers = [];

    /**
     * @return AlarmObservable
     */
    public function setMessage(Message $message)
    {
        $this->message = $message;
        $scene = $message->getProp(Notify::MARK_SCENE);
        $result = $this->levelAlarm($scene);
        if (! $result) {
            $this->commonAlarm($scene);
        }
        // 工作流处理，是否发送工作流通知
        $workflowScene = $message->getProp(Workflow::MARK_WORKFLOW_NOTIFY);
        if ($workflowScene !== null) {
            $message->setProp(Notify::MARK_SCENE, $workflowScene);
            $message->setProp('context', ['type' => $workflowScene]);
            $this->commonAlarm($workflowScene);
        }

        return $this;
    }

    public function notify(): void
    {
        // dump('observers:' . $this->countObserver());
        foreach ($this->observers as $obs) {
            $obs->handle($this);
        }
    }

    public function attach(AlarmChannelObserver $observer)
    {
        $this->observers[] = $observer;
    }

    public function detach(AlarmChannelObserver $observer)
    {
        $newObservers = [];
        foreach ($this->observers as $key => $obs) {
            if ($obs !== $observer) {
                $newObservers[] = $obs;
            }
        }
        $this->observers = $newObservers;
    }

    /**
     * @return int
     */
    public function countObserver()
    {
        return count($this->observers);
    }

    /**
     * 分级告警.
     * @param $scene
     * @return bool true:懒惰模式命中不需要commonAlarm  false:懒惰没命中，或者非懒惰，都需要继续commonAlarm
     */
    protected function levelAlarm($scene)
    {
        $payload = $this->message->getSourcePayload();
        $receiver = $this->message->getTask()->getReceiver();
        $dispatch = $receiver->getDispatch();
        $mode = $receiver->getDispatchMode();

        foreach ($dispatch as $key => $value) {
            $conditions = $value['conditions'];
            $channels = $value['channels'] ?? [];
            foreach ($conditions as $rules) {
                if (ConditionArr::matchRule($rules['rule'], $payload)) {
                    if ($channels) {
                        $this->aloneAlarm($scene, $channels);
                    }
                    if ($mode == AlarmGroup::RECV_DISPATCH_MODE_LAZY) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 单独配置告警，并注册.
     *
     * @param $scene
     * @param $channels
     */
    protected function aloneAlarm($scene, $channels)
    {
        // dump('aloneAlarm.$scene=' . $scene);
        $sceneClass = self::SCENE_CLASS[$scene] ?? null;
        if (! $sceneClass) {
            return;
        }

        $payload = $this->message->getSourcePayload();
        $context = $this->message->getProp('context', []);
        $workflow = $this->message->getProp(Workflow::MARK_WORKFLOW_DATA, []);

        $this->alarmType = make($sceneClass);
        $this->alarmType->setMessage($this->message);
        $this->alarmType->setExactReceiver($channels);

        switch ($scene) {
            case AlarmTpl::SCENE_COMPRESSED:
            case AlarmTpl::SCENE_NOT_COMPRESS:
                $this->initObserver($scene, $payload, $context);
                break;
            case AlarmTpl::SCENE_UPGRADE:
                $payload['rule'] = $context['rule'];
                $payload['context'] = $context;
                $this->initObserver($scene, $payload, $context);
                break;
            case AlarmTpl::SCENE_RECOVERY:
                $this->initObserver($scene, $payload);
                break;
            // 工作流
            case AlarmTpl::SCENE_GENERATED:
            case AlarmTpl::SCENE_REMIND_PENDING:
            case AlarmTpl::SCENE_REMIND_PROCESSING:
                $payload['workflow'] = $workflow;
                $payload['context'] = $context;
                $this->initObserver($scene, $payload);
                break;
            default:
                break;
        }
    }

    /**
     * 通用告警，配置信息，并注册观察者.
     * @param $scene
     */
    protected function commonAlarm($scene)
    {
        // dump('$scene=' . $scene);
        $sceneClass = self::SCENE_CLASS[$scene] ?? null;
        if (! $sceneClass) {
            return;
        }

        $payload = $this->message->getSourcePayload();
        $context = $this->message->getProp('context', []);
        $workflow = $this->message->getProp(Workflow::MARK_WORKFLOW_DATA, []);

        $this->alarmType = make($sceneClass);
        $this->alarmType->setMessage($this->message);

        switch ($scene) {
            case AlarmTpl::SCENE_COMPRESSED:
            case AlarmTpl::SCENE_NOT_COMPRESS:
                $this->alarmType->setReceiver($payload);
                $this->initObserver($scene, $payload, $context);
                break;
            case AlarmTpl::SCENE_UPGRADE:
                $payload['rule'] = $context['rule'];
                $payload['context'] = $context;
                $this->alarmType->setReceiver($payload);
                $this->initObserver($scene, $payload, $context);
                break;
            case AlarmTpl::SCENE_RECOVERY:
                $this->alarmType->setReceiver($payload);
                $this->initObserver($scene, $payload);
                break;
            // 工作流通知
            case AlarmTpl::SCENE_GENERATED:
            case AlarmTpl::SCENE_REMIND_PENDING:
            case AlarmTpl::SCENE_REMIND_PROCESSING:
                $payload['workflow'] = $workflow;
                $payload['context'] = $context;
                $this->alarmType->setReceiver($payload);
                $this->initObserver($scene, $payload);
                break;
        }
    }

    /**
     * @param $scene
     */
    private function initObserver($scene, array $data = [], array $options = []): void
    {
        $task = $this->message->getTask();
        $alarmTemplate = $task->getTemplate()->getTemplate();
        $workflowTemplate = $task->getWorkflow()->getTemplate();
        $alarmTemplate = array_merge($alarmTemplate, $workflowTemplate);
        $receivers = $this->alarmType->getReceiver();

        foreach ($receivers as $channel => $receiver) {
            if (empty($receiver)) {
                continue;
            }
            $class = self::OBSERVER_CLASS[$channel] ?? null;
            if (! $class) {
                continue;
            }
            /** @var AlarmChannelObserver $observer */
            $observer = make($class);
            $observer->setScene($scene);
            $observer->setMessage($this->message);
            $observer->setData(['data' => $data, 'options' => $options]);
            $observer->setChannel($channel);
            $observer->setReceiver($receiver);
            if ($channel === AlarmGroup::CHANNEL_EMAIL) {
                // email 特殊处理
                $subject = $alarmTemplate[$scene][$channel]['subject'] ?? $workflowTemplate[$scene][$channel]['subject'] ?? '';
                $observer->setSubject($subject);
            }
            $payload = '';
            if ($channel != AlarmGroup::CHANNEL_WEBHOOK) {
                $payload = $this->alarmType->performPayload(
                    $data,
                    $alarmTemplate[$scene][$channel] ?? $workflowTemplate[$scene][$channel] ?? [] // $scene 场景， $channel 渠道
                );
            }
            $observer->setPayload($payload);
            $this->attach($observer);
        }
    }
}
