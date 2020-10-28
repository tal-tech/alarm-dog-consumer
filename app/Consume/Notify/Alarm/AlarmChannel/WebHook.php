<?php

declare(strict_types=1);

namespace App\Consume\Notify\Alarm\AlarmChannel;

use App\Common\CustomerCommon;
use App\Consume\Notify\Notify;
use App\Model\AlarmGroup;
use App\Model\AlarmTemplate as AlarmTpl;
use Dog\Noticer\Component\Guzzle;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use Throwable;

class WebHook extends AlarmChannelObserver
{
    /**
     * @var array
     */
    public $dataType = [
        'event' => [
            AlarmTpl::SCENE_COMPRESSED => 'ALARM',
            AlarmTpl::SCENE_NOT_COMPRESS => 'ALARM',
            AlarmTpl::SCENE_UPGRADE => 'UPGRADE',
            AlarmTpl::SCENE_RECOVERY => 'RECOVERY',
            AlarmTpl::SCENE_GENERATED => 'WORKFLOW',
            AlarmTpl::SCENE_REMIND_PENDING => 'WORKFLOW',
        ],
    ];

    protected $name = AlarmGroup::CHANNEL_WEBHOOK;

    /**
     * @param $msgType
     * @throws Exception
     */
    public function sendMsg($msgType)
    {
        $urls = $this->getReceiver();
        /*
         * @var GuzzleHttpClient $client
         */
        if (is_array($urls)) {
            foreach ($urls as $md5 => $uri) {
                $num = 0;
                /*
                 * @var GuzzleHttpClient $client
                 */
                continueSend:
                $client = null;
                if (! isset(CustomerCommon::$webHookPool[$md5])) {
                    $guzzleConfig = config('noticer.guzzle');
                    $guzzleConfig['options']['timeout'] = 1;
                    $guzzleConfig['options']['base_uri'] = $uri;
                    $guzzleConfig['options']['swoole']['timeout'] = 1;
                    CustomerCommon::$webHookPool[$md5] = Guzzle::create($guzzleConfig);
                }
                $scene = $this->getScene();
                $resData = $this->getData();
                $filterData = $this->performData($scene, $resData);
                $sendPayload = [
                    'event' => $this->dataType['event'][$scene],
                    'type' => $filterData['type'],
                    'data' => $filterData['data'],
                    'extra' => [],
                ];
                $client = CustomerCommon::$webHookPool[$md5];
                try {
                    $client->post($uri, ['json' => $sendPayload]);
                } catch (Throwable $e) {
                    unset(CustomerCommon::$webHookPool[$md5]);
                    $num++;
                    if ($num >= 3) {
                        throw new Exception('send fail' . $e->getMessage());
                    }
                    goto continueSend;
                }
            }
        }
    }

    /**
     * @param $scene
     * @param array $params
     * @return array
     */
    public function performData($scene, $params = [])
    {
        $data = $params['data'];
        $options = $params['options'];
        $type = '';
        $tmpData = [];
        $task = $this->getTask();
        $tmpData['task']['id'] = $task['task_id'] ?: 0;
        $tmpData['task']['name'] = $task['name'];

        $history = [];
        $history['id'] = $data['id'] ?? '';
        $history['batch'] = $data['batch'] ?? '';
        $history['metric'] = $data['metric'] ?? '';
        $history['uuid'] = $data['uuid'] ?? '';
        $history['level'] = $data['level'] ?? '';
        $history['ctn'] = $data['ctn'] ?? '';
        $history['notice_time'] = $data['notice_time'] ?? '';

        switch ($scene) {
            case Notify::SCENE_COMPRESSED:
            case Notify::SCENE_NOT_COMPRESS:
                $type = $options['type'];
                switch ($type) {
                    case 'not_save_db':
                        $tmpData['msg']['uuid'] = $data['uuid'] ?? '';
                        $tmpData['msg']['leve'] = $data['level'] ?? '';
                        $tmpData['msg']['ctn'] = $data['ctn'] ?? '';
                        $tmpData['msg']['notice_time'] = $data['notice_time'] ?? '';
                        break;
                    case 'compressed':
                    case 'compress_not_match':
                    case 'compress_disable':
                        $tmpData['history'] = $history;
                        break;
                }
                break;
            case Notify::SCENE_UPGRADE:
                $type = 'upgrade';
                $tmpData['history'] = $history;
                break;
            case Notify::SCENE_RECOVERY:
                $type = $task['flag_save_db'] ? 'recovery' : 'notSaveDb';
                $tmpData['history'] = $history;
                break;
            case AlarmTpl::SCENE_GENERATED:
                $type = 'generated';
                $tmpData['history'] = $history;
                $tmpData['workflow']['id'] = $data['workflow']['id'] ?? '';
                break;
            case AlarmTpl::SCENE_REMIND_PENDING:
                $type = 'remind_pending';
                $tmpData['history'] = $history;
                $tmpData['workflow']['id'] = $data['workflow']['id'] ?? '';
                break;
            case AlarmTpl::SCENE_REMIND_PROCESSING:
                $type = 'remind_processing';
                $tmpData['history'] = $history;
                $tmpData['workflow']['id'] = $data['workflow']['id'] ?? '';
                break;
        }

        return ['type' => $type, 'data' => $tmpData];
    }
}
