<?php

declare(strict_types=1);

namespace App\Consume\Pipeline;

use App\Consume\Compress\Compress;
use App\Consume\Filter\Filter;
use App\Consume\Message\Message;
use App\Consume\Notify\Notify;
use App\Consume\Recovery\Recovery;
use App\Consume\Upgrade\Upgrade;
use App\Consume\Workflow\Workflow;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Throwable;

class Plant
{
    // 下一步顺序执行
    public const PIPELINE_STATUS_NEXT = 0;

    // 下一步终止执行
    public const PIPELINE_STATUS_END = -1;

    // 下一步跳转执行
    public const PIPELINE_STATUS_JUMP = 1;

    // 下一步从跳转点继续执行
    public const PIPELINE_STATUS_NEXT_FROM_JUMP = 2;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var PipelineAbstract[]
     */
    protected $pipelines = [];

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
        $this->logger = $this->container->get(LoggerFactory::class)->get('pipeline');
        $this->formatter = $this->container->get(FormatterInterface::class);

        $this->pipelines = [
            $container->get(Recovery::class),
            $container->get(Filter::class),
            $container->get(Compress::class),
            $container->get(Upgrade::class),
            $container->get(Workflow::class),
            $container->get(Notify::class),
        ];
    }

    /**
     * @param int $next
     * @return bool
     */
    public function consume(Message $message, $next = Plant::PIPELINE_STATUS_NEXT)
    {
        // dump($message->getSourcePayload()['uuid'] . '@' . getmypid() . ',next=' . $next);
        // Coroutine::sleep(1);
        foreach ($this->pipelines as $pipeline) {
            // 对于NEXT_FROM_JUMP进行拦截处理
            if ($next == static::PIPELINE_STATUS_NEXT_FROM_JUMP) {
                // 重置next为顺序执行
                if ($pipeline !== $message->getJumpToPipeline()) {
                    // dump('skip: ' . $pipeline->getName());
                    continue;
                }
                // $next = static::PIPELINE_STATUS_NEXT;
            }

            // 使用do while用于处理JUMP的情况
            do {
                // dump('normal: ' . $pipeline->getName());
                try {
                    $next = $pipeline->handle($message);
                } catch (JumpException $e) {
                    $next = $e->getPipelineStatus();
                } catch (Throwable $e) {
                    // dump($e->getMessage());
                    // 记录日志
                    $this->logger->error($this->formatter->format($e));
                    // 终止整个pipeline，直接return，因异常return false
                    return false;
                }

                // 状态处理
                if ($next == static::PIPELINE_STATUS_JUMP) {
                    // 替换pipeline，继续执行
                    $pipeline = $message->getJumpToPipeline();
                } elseif ($next == static::PIPELINE_STATUS_END) {
                    // 终止整个pipeline，直接return
                    return true;
                } else {
                    // 终止do while，进入到foreach，
                    // 如果next为NEXT_FROM_JUMP，透传next交给do while上层条件处理
                    // dump('next:' . $next);
                    break;
                }
            } while (true);
        }

        return true;
    }
}
