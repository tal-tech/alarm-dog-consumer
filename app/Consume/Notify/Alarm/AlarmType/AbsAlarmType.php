<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmType;

use App\Consume\Message\Message;
use App\Consume\Message\Task\Compress;
use App\Consume\Notify\Notify;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Monolog\Utils;

abstract class AbsAlarmType
{
    protected $name;

    /**
     * @var Message
     */
    protected $message;

    /**
     * 告警接收人.
     *
     * @var array
     */
    protected $receiver = [];

    /**
     * 告警消息体.
     *
     * @var string
     */
    protected $payload = '';

    public function setMessage(Message $message)
    {
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function getReceiver(): array
    {
        return $this->receiver;
    }

    /**
     * 设置通用告警，升级，通知人.
     */
    public function setReceiver(array $data): AbsAlarmType
    {
        $this->receiver = $this->performReceiver($data);
        $receiverLogger = ApplicationContext::getContainer()
            ->get(LoggerFactory::class)
            ->get('send_notice_of_receiver', 'default');
        $receiverLogger->notice(Utils::jsonEncode($this->receiver, null, true));
        return $this;
    }

    /**
     * 设置确切告警通知人.
     */
    public function setExactReceiver(array $receiver = []): AbsAlarmType
    {
        $this->receiver = $receiver;
        return $this;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function performPayload($data = [], array $tpl = [])
    {
        if (! $tpl) {
            return '';
        }
        $replaceSearch = [];
        $replaceVars = [];
        $task = $this->message->getTask();
        foreach ($tpl['vars_split'] as $varName => $varNameArr) {
            if (count($varNameArr)) {
                $keyType = $varNameArr[0];
                // 移除 keyType
                array_shift($varNameArr);
                $target = [];
                switch ($keyType) {
                    case 'task':
                        $target = $task->toArray();
                        if ($this->message->getTask()->isEnableCompress()) {
                            //收敛数据
                            $method = $task->getCompress()->getMethod();
                            $stratrgy = $task->getCompress()->getStratrgy();
                            $target['compress_method'] = Compress::COMPRESS_METHOD[$method];
                            $target['compress_type'] = Compress::COMPRESS_TYPE[$stratrgy];
                        }
                        break;
                    case 'context':
                        // 告警升级特定数据
                        $data['context']['rule']['level'] = Notify::ALARM_LEVEL[$data['context']['rule']['level']] ?? $data['context']['rule']['level'];
                        $target = $data['context'];
                        break;
                    case 'workflow': // 告警工作流特定数据
                        $target = $data['workflow'];
                        break;
                    case 'history':
                        if (is_string($data['ctn'])) {
                            $data['ctn'] = json_decode($data['ctn'], true);
                        }
                        if (isset($data['receiver']) && is_string($data['receiver'])) {
                            $data['receiver'] = json_decode($data['receiver'], true);
                        }
                        $target = $data;
                        $target['level'] = Notify::ALARM_LEVEL[$target['level']] ?? $target['level'];
                        break;
                    case 'common':
                        $target['env'] = env('ENVNAME');
                        break;
                }
                $tValue = data_get($target, $varNameArr, '{' . $varName . '}');
                $searchValue = is_array($tValue) ? Utils::jsonEncode($tValue, null, true) : $tValue;
                if ($searchValue !== null) {
                    $replaceSearch[] = '{' . $varName . '}';
                    $replaceVars[] = $searchValue;
                }
            }
        }
        return str_replace($replaceSearch, $replaceVars, $tpl['template']);
    }

    /**
     * 解析告警接收者.
     * @return array
     */
    protected function performReceiver(array $data = [])
    {
        // 有没有自定义接收人
        $receiver = $data['receiver'] ?? [];
        if (! empty($receiver)) {
            $filterReceiver = $data['receiver']->getDefaultChannels();
        } else {
            $filterReceiver = $this->message->getTask()->getReceiver()->getDefaultChannels();
        }
        return $filterReceiver;
    }

    /**
     * 解析工作流通知接收者.
     * @return array|mixed
     */
    protected function workFlowPerformReceiver(array $data = [])
    {
        $filterReceiver = $this->message->getTask()->getReceiver()->getDefaultChannels();
        if (isset($data['context']) && ! empty($data['context'])) {
            if (isset($data['context']['reuse_receiver']) && $data['context']['reuse_receiver']) {
                $filterReceiver = $this->message->getTask()->getReceiver()->getDefaultChannels();
            } elseif (! empty($data['context']['receiver'])) {
                $filterReceiver = $data['context']['receiver']->getDefaultChannels();
            }
        }
        return $filterReceiver;
    }
}
