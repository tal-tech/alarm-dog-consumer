<?php

declare(strict_types=1);

use App\Consume\Driver\MqProxy;
use App\Consume\Driver\Redis;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Hyperf\Guzzle\RetryMiddleware;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;

return [
    'driver' => env('CONSUMER_DRIVER', 'mqproxy'),
    // 数据同步间隔，单位毫秒
    'data_sync_interval' => (int) env('CONSUMER_DATA_SYNC_INTERVAL', 20000),
    // 数据同步文件缓存路径
    'data_sync_file_cache_path' => env('CONSUMER_DATA_SYNC_FILE_CACHE_PATH', BASE_PATH . '/runtime/datasync.cache'),

    /*
     * 消费者驱动
     * key为event worker的workerId，value为driver的具体实现及配置
     */
    'drivers' => [
        'mqproxy' => [
            'class' => MqProxy::class,
            'config' => [
                'proxy' => explode(',', env('CONSUMER_MQPROXY_PROXY', 'http://10.90.100.130:8080')),
                'appid' => env('CONSUMER_MQPROXY_APPID'),
                'appkey' => env('CONSUMER_MQPROXY_APPKEY'),
                'topic' => env('CONSUMER_MQPROXY_TOPIC', 'alarm-dog'),
                'group' => env('CONSUMER_MQPROXY_GROUP', 'alarm_consumer'),
                'reset' => env('CONSUMER_MQPROXY_RESET', 'earlies'),
                'commit_timeout' => (int) env('CONSUMER_MQPROXY_COMMIT_TIMEOUT', 20),
                'max_msgs' => (int) env('CONSUMER_MQPROXY_MAX_MSGS', 100),
                'max_consume_times' => (int) env('CONSUMER_MQPROXY_MAX_COMSUME_TIMES', 3),
                // 最大的分区数量，预生成分区提高offset ack速度
                'max_partition_size' => (int) env('CONSUMER_MQPROXY_MAX_PARTITION_SIZE', 8),
                // 最大可以同时消费的消息数量，目的是为了保护task worker不会积压大量消息
                'max_consuming_count' => (int) env('CONSUMER_MQPROXY_MAX_CONSUMING_COUNT', 5000),
                'guzzle' => [
                    // guzzle原生配置选项
                    'options' => [
                        'base_uri' => null,
                        'timeout' => 3.0,
                        'verify' => false,
                        'http_errors' => false,
                        'headers' => [
                            'Connection' => 'keep-alive',
                        ],
                        // hyperf集成guzzle的swoole配置选项
                        'swoole' => [
                            'timeout' => 10,
                            'socket_buffer_size' => 1024 * 1024 * 2,
                        ],
                    ],
                    // guzzle中间件配置
                    'middlewares' => [
                        // // 失败重试中间件
                        // 'retry' => function () {
                        //     return make(RetryMiddleware::class, [
                        //         'retries' => 1,
                        //         'delay' => 10,
                        //     ])->getMiddleware();
                        // },
                        // // 请求日志记录中间件
                        // 'logger' => function () {
                        //     // $format中{response}调用$response->getBody()会导致没有结果输出
                        //     $format = ">>>>>>>>\n{request}\n<<<<<<<<\n{res_headers}\n--------\n{error}";
                        //     $formatter = new MessageFormatter($format);
                        //     $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('mqproxy');

                        //     return Middleware::log($logger, $formatter, 'debug');
                        // }
                    ],
                    // hyperf集成guzzle的连接池配置选项
                    'pool' => [
                        'option' => [
                            'max_connections' => (int) env('CONSUMER_MQPROXY_POOL_SIZE', 10),
                        ],
                    ],
                ],
            ],
        ],
        'redis' => [
            'class' => Redis::class,
            'config' => [
                // 最大可以同时消费的消息数量，目的是为了保护task worker不会积压大量消息
                'max_consuming_count' => (int) env('CONSUMER_MQPROXY_MAX_CONSUMING_COUNT', 5000),
                'instance' => env('CONSUMER_REDIS_INSTANCE', 'default'),
                'key_message' => env('CONSUMER_REDIS_KEY_MESSAGE', 'alarm-dog.queue.message'),
                'timeout' => (int) env('CONSUMER_REDIS_TIMEOUT', 10),
                'key_consuming' => env('CONSUMER_REDIS_KEY_CONSUMING', 'alarm-dog.queue.consuming'),
                'check_ack_interval' => (int) env('CONSUMER_REDIS_CHECK_ACK_INTERVAL', 5000),
            ],
        ],
    ],
];
