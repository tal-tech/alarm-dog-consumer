<?php

declare(strict_types=1);

namespace App\Consume\Pipeline;

use App\Consume\Message\Message;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class PipelineAbstract
{
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
        $this->logger = $this->container->get(LoggerFactory::class)->get('pipeline');
        $this->formatter = $this->container->get(FormatterInterface::class);

        $this->init();
    }

    /**
     * 工作流处理.
     */
    abstract public function handle(Message $message);

    /**
     * 工作流名称.
     */
    abstract public function getName(): string;

    /**
     * 初始化.
     */
    protected function init()
    {
    }

    /**
     * 入库存储.
     */
    protected function saveDb(Message $message)
    {
        // 入库存储，不要强依赖db，即使挂掉可以使用假数据等让告警继续
        try {
            if ($message->getTask()->isSaveDb()) {
                $message->saveDb();
            }
        } catch (Throwable $e) {
            $this->logger->error($this->formatter->format($e));
        }
    }
}
