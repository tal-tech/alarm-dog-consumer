<?php

declare(strict_types=1);

namespace App\Support;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Hyperf\Guzzle\PoolHandler;
use Hyperf\Utils\Coroutine;

class GuzzleCreator
{
    /**
     * 创建guzzle客户端.
     *
     * @param array $config
     * @return Client
     */
    public static function create($config = [])
    {
        $stack = static::createHandler($config);
        static::pushMiddlewares($stack, $config);

        $guzzleConfig = $config['options'] ?? [];
        $guzzleConfig['handler'] = $stack;

        return new Client($guzzleConfig);
    }

    /**
     * 创建guzzle handler.
     *
     * @param array $config
     * @return HandlerStack
     */
    protected static function createHandler($config = [])
    {
        $handler = null;
        if (Coroutine::inCoroutine()) {
            $handler = make(PoolHandler::class, $config['pool'] ?? []);
        }

        return HandlerStack::create($handler);
    }

    /**
     * Push guzzle客户端中间件.
     *
     * @param array $config
     */
    protected static function pushMiddlewares(HandlerStack $stack, $config = [])
    {
        foreach ($config['middlewares'] ?? [] as $name => $middleware) {
            if (is_callable($middleware)) {
                $middleware = call_user_func($middleware);
            }
            $stack->push($middleware, $name);
        }
    }
}
