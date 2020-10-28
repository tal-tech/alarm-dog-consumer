<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\DataObserver;
use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\MessageObserver;
use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\PayloadObserver;
use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\ReceiverObserver;
use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\SceneObserver;
use App\Consume\Notify\Alarm\AlarmChannel\ExtendObserver\SubjectObserver;
use App\Consume\Notify\Alarm\Observable;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate as AlarmTpl;
use GuzzleHttp\Exception\TransferException;
use Hyperf\Contract\ContainerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\Coroutine;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class AlarmChannelObserver
{
    use MessageObserver;
    use ReceiverObserver;
    use PayloadObserver;
    use DataObserver;
    use SceneObserver;
    use SubjectObserver;

    public static $alarmSignPrefix = '【哮天犬】';

    public static $alarmPrefix = '哮天犬监控告警平台：';

    public static $alarmSuffix;

    public static $alarmHtmlSuffix;

    //email 主题

    protected $formatClass = [];

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $this->container->get(LoggerFactory::class)->get($this->name);
        $this->formatter = $this->container->get(FormatterInterface::class);
        $this->init();
    }

    /**
     * 获取trace接口名称.
     * @param string $channel
     * @return string
     */
    public function getTraceInterfaceName($channel = '')
    {
        $scene = $this->getScene();
        $allData = $this->getData();
        $data = $allData['data'];
        $options = $allData['options'];
        $taskId = isset($data['task_id']) ? $data['task_id'] : $data['taskid'];
        $type = isset($options['type']) ? $options['type'] : '';
        return $taskId . '--' . $scene . '--' . $type . '--' . $channel;
    }

    /**
     * 获取上下文数据.
     * @return array
     */
    public function getContextData()
    {
        return [
            'scene' => $this->getScene(),
            'data' => $this->getData(),
            'receiver' => $this->getReceiver(),
            'payload' => $this->getPayload(),
            'channel' => $this->getChannel(),
        ];
    }

    public function handle(Observable $observable)
    {
        Coroutine::create(
            function () {
                try {
                    $template = $this->getTask()->getTemplate()->getTemplate();
                    $workflowTemplate = $this->getTask()->getWorkflow()->getTemplate();
                    $template = array_merge($template, $workflowTemplate);
                    $format = $template[$this->getScene()][$this->getChannel()]['format'] ?? '';

                    $msgType = null;
                    $date = "\n" . date('Y-m-d H:i:s');
                    if ($format == AlarmTpl::FORMAT_TEXT) {
                        if (in_array($this->name, [AlarmGroup::CHANNEL_SMS, AlarmGroup::CHANNEL_PHONE])) {
                            $msgType = self::$alarmPrefix . $this->getPayload();
                        } else {
                            if ($this->name == AlarmGroup::CHANNEL_EMAIL) { // 邮件内容处理
                                $msgType = $this->getPayload() . self::$alarmSuffix;
                            } else {
                                $className = $this->formatClass[$format] ?? null;
                                if ($className) {
                                    $msgType = new $className(self::$alarmSignPrefix . $this->getPayload() . $date);
                                } else {
                                    $msgType = $this->getPayload() . self::$alarmSuffix;
                                }
                            }
                        }
                    } elseif ($format == AlarmTpl::FORMAT_MARKDOWN) {
                        $title = $template[$this->getScene()][$this->getChannel()]['title'] ?? '';
                        $className = $this->formatClass[$format] ?? null;
                        if ($className) {
                            $msgType = new $className(
                                self::$alarmPrefix . $title,
                                $this->getPayload() . self::$alarmSuffix . "\n" . $date
                            );
                        } else {
                            $msgType = $this->getPayload() . self::$alarmSuffix;
                        }
                    } elseif ($format == AlarmTpl::FORMAT_HTML) {
                        // 邮件内容处理
                        $msgType = $this->getPayload() . self::$alarmHtmlSuffix;
                    }

                    if ($msgType) {
                        $this->sendMsg($msgType);
                    } else {
                        if ($this->getChannel() == AlarmGroup::CHANNEL_WEBHOOK) {
                            $this->sendMsg($msgType);
                        } else {
                            var_dump($this->message);
                        }
                    }
                } catch (TransferException $e) {
                    $this->logger->warning($this->formatter->format($e));
                } catch (Throwable $e) {
                    $this->logger->warning($this->formatter->format($e));
                }
            }
        );
    }

    abstract public function sendMsg($msgType);

    protected function init()
    {
        $admin_url = config('app.admin_url');
        self::$alarmSuffix = '

---

此告警由 [哮天犬监控告警平台](' . $admin_url . ') 发出';
        self::$alarmHtmlSuffix = '<p><br></p><p style="font-size: 12px; color: #aaa">本邮件由<a href="' . $admin_url . '" target="_blank" style="color: #3b73af">哮天犬监控告警平台</a>发出，请勿回复。</p>';
    }

    /**
     * 获取任务id.
     * @return int
     */
    protected function getTaskId()
    {
        $task = $this->getTask();
        return $task['id'] ?: 0;
    }

    protected function getReceivers($users)
    {
        $receivers = [];
        foreach ($users as $user) {
            if (! empty($user['phone'])) {
                $receivers[$user['phone']] = $user['phone'];
            }
        }
        return array_values($receivers);
    }
}
