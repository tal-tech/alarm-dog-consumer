<?php

declare(strict_types=1);

namespace App\Common;

use GuzzleHttp\Client;
use Hyperf\Guzzle\HandlerStackFactory;
use Hyperf\Guzzle\RetryMiddleware;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class CustomerCommon
{
    public static $webHookPool = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->get('consumer_common', 'default');
    }

    /**
     * Retry an operation a given number of times.
     *
     * @param $times
     * @param int $sleep
     * @throws \Throwable
     * @return mixed
     */
    public static function retry($times, callable $callback, $sleep = 0)
    {
        beginning:
        try {
            return $callback();
        } catch (\Throwable $e) {
            if (--$times < 0) {
                throw $e;
            }
            if ($sleep) {
                usleep($sleep * 1000);
            }
            goto beginning;
        }
    }

    public static function getCallbackClient($uri)
    {
        $factory = new HandlerStackFactory();
        $stack = $factory->create(
            [
                'min_connections' => 2,
                'max_connections' => (int) (10),
                'wait_timeout' => 3.0,
                'max_idle_time' => 60,
            ],
            [
                'retry' => [RetryMiddleware::class, [1, 10]],
            ]
        );

        return make(
            Client::class,
            [
                'config' => [
                    'base_uri' => $uri,
                    'handler' => $stack,
                    'timeout' => 0.2,
                ],
            ]
        );
    }

    /**
     * 配置trace告警.
     */
    public static function initStatsConfig()
    {
    }

    /**
     * get tick object.
     * @param $interface
     * @param $traceModuleId
     * @return Tick
     */
    public static function getTick($interface, $traceModuleId)
    {
    }

    /**
     * @return mixed|string
     */
    public static function getLocalIp()
    {
        $currentIp = '127.0.0.1';
        $ipArr = swoole_get_local_ip();
        if (! empty($ipArr)) {
            $currentIp = current($ipArr);
        }

        return $currentIp;
    }

    /**
     * 上报信息.
     *
     * @param $tick
     * @param $status
     * @param $code
     * @param $ip
     */
    public static function reportTick($tick, $status, $code, $ip)
    {
    }

    /**
     * record send fail msg.
     * @param array $ctx
     */
    public static function recordLog(object $e, string $sendContent, array $receiver, $ctx = [])
    {
    }
}
