<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Model\AlarmGroup;
use App\Support\SimpleCollection;

class Receiver extends SimpleCollection
{
    public function __construct(
        $defaultChannels,
        $enableDispatch = false,
        $dispatch = [],
        $dispatchMode = AlarmGroup::RECV_DISPATCH_MODE_LAZY
    ) {
        $this->elements = [
            'default' => $defaultChannels,
            'enableDispatch' => $enableDispatch,
            'dispatch' => $dispatch,
            'dispatchMode' => $dispatchMode,
        ];
    }

    /**
     * @return array
     */
    public function getDefaultChannels()
    {
        return $this->elements['default'];
    }

    /**
     * @return bool
     */
    public function isEnableDispatch()
    {
        return $this->elements['enableDispatch'];
    }

    /**
     *[
     *  {
     *    // 分级条件
     *    "conditions": [
     *       {
     *         "rule": [
     *            {
     *             "field": "ctn.cpu2",
     *             "operator": "gt",
     *             "threshold": "80"
     *           }
     *         ]
     *       }
     *    ],
     *    // 合并之后的通知渠道
     *    "channels": {
     *      "yachworker": [
     *           999999
     *       ]
     *    }
     *  }
     *].
     *
     * @return array
     */
    public function getDispatch()
    {
        return $this->elements['dispatch'];
    }

    /**
     * @return int
     */
    public function getDispatchMode()
    {
        return $this->elements['dispatchMode'];
    }

    /**
     * 解析、合并channels配置.
     *
     * @return array
     */
    public static function parseAndMergeChannels(?array $receiver, ?array $alarmGroups, ?array $users)
    {
        [$channels, $groupChannels] = Receiver::parseGroupChannels($receiver, $alarmGroups);

        return Receiver::mergeChannels($channels, $groupChannels, $users);
    }

    /**
     * 解析出groupChannels、channels.
     *
     * @return array
     */
    public static function parseGroupChannels(?array $receiver, ?array $alarmGroups)
    {
        $channels = $receiver['channels'] ?? [];

        $groupChannels = [];
        foreach ($receiver['alarmgroup'] ?? [] as $groupId) {
            if (isset($alarmGroups[$groupId], $alarmGroups[$groupId]['receiver']['channels'])) {
                $groupChannels[] = $alarmGroups[$groupId]['receiver']['channels'];
            }
        }

        return [$channels, $groupChannels];
    }

    /**
     * 合并channels.
     *
     * @return array
     */
    public static function mergeChannels(?array $channels, ?array $groupChannels, ?array $users)
    {
        array_unshift($groupChannels, $channels);

        $merged = [];

        // 用户uid系列
        foreach (
            [
                AlarmGroup::CHANNEL_SMS,
                AlarmGroup::CHANNEL_PHONE,
                AlarmGroup::CHANNEL_EMAIL,
                AlarmGroup::CHANNEL_DINGWORKER,
                AlarmGroup::CHANNEL_YACHWORKER,
            ] as $channel
        ) {
            $preMerge = [];
            foreach ($groupChannels as $channels) {
                // 不包含该渠道的跳过
                if (empty($channels[$channel]) || ! is_array($channels[$channel])) {
                    continue;
                }
                foreach ($channels[$channel] as $uid) {
                    // 再次处理，避免有为0、负数的情况
                    $uid = intval($uid);
                    if ($uid <= 0 || ! array_key_exists($uid, $users)) {
                        continue;
                    }
                    // 使用相同key去重数据
                    $preMerge[$uid] = $users[$uid];
                }
            }

            // 该渠道数据不为空，则缓存
            if (! empty($preMerge)) {
                $merged[$channel] = array_values($preMerge);
            }
        }

        // 机器人系列
        foreach (
            [
                AlarmGroup::CHANNEL_DINGGROUP,
                AlarmGroup::CHANNEL_YACHGROUP,
            ] as $channel
        ) {
            $preMerge = [];
            foreach ($groupChannels as $channels) {
                // 不包含该渠道的跳过
                if (empty($channels[$channel]) || ! is_array($channels[$channel])) {
                    continue;
                }
                foreach ($channels[$channel] as $robot) {
                    // 使用相同key去重数据
                    $preMerge[$robot['webhook']] = $robot;
                }
            }

            // 该渠道数据不为空，则缓存
            if (! empty($preMerge)) {
                $merged[$channel] = array_values($preMerge);
            }
        }

        // webhook
        foreach ($groupChannels as $channels) {
            // webhook采用优先匹配原则，先配置的作为有效配置
            if (! empty($channels[AlarmGroup::CHANNEL_WEBHOOK])) {
                $merged[AlarmGroup::CHANNEL_WEBHOOK] = [
                    'url' => $channels[AlarmGroup::CHANNEL_WEBHOOK]['url'],
                ];
                break;
            }
        }

        return $merged;
    }

    /**
     * 解析分级告警.
     * @return array
     */
    public static function parseDispatchAndMergeChannels(?array $dispatch, ?array $alarmGroups, ?array $users)
    {
        $merged = [];
        foreach ($dispatch as $item) {
            $merged[] = [
                'conditions' => $item['conditions'],
                'channels' => Receiver::parseAndMergeChannels($item['receiver'], $alarmGroups, $users),
            ];
        }

        return $merged;
    }
}
