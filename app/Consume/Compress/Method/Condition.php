<?php

declare(strict_types=1);

namespace App\Consume\Compress\Method;

use App\Consume\Message\Message;
use App\Support\ConditionArr;

/**
 * 条件收敛.
 */
class Condition extends MethodAbstract
{
    /**
     * 计算收敛指标.
     *
     * @return string
     */
    public function handle(Message $message): ?string
    {
        $payload = $message->getSourcePayload();
        $conditions = $message->getTask()->getCompress()['conditions'];

        if (! ($condition = ConditionArr::match($conditions, $payload))) {
            // 未命中收敛直接返回空指标，无需计算指标
            return null;
        }

        // 计算收敛指标
        return $this->computingConditionMetric($message, $condition);
    }

    /**
     * 计算条件收敛指标.
     * @param $condition
     * @return string
     */
    protected function computingConditionMetric(Message $message, $condition)
    {
        $payload = $message->getSourcePayload();

        $items = [];
        foreach ($condition['rule'] as $rule) {
            switch ($rule['operator']) {
                case ConditionArr::OP_EQ_SELF:
                    [$exist, $value] = ConditionArr::getValue($payload, $rule['field_split']);
                    $items[] = sprintf('%s#%s#%s', $rule['field'], $rule['operator'], $value);
                    break;
                case ConditionArr::OP_IN:
                case ConditionArr::OP_NOT_IN:
                    $value = implode('|', $rule['threshold']);
                    $items[] = sprintf('%s#%s#%s', $rule['field'], $rule['operator'], $value);
                    break;
                case ConditionArr::OP_ISSET:
                case ConditionArr::OP_NOT_ISSET:
                    $items[] = sprintf('%s#%s', $rule['field'], $rule['operator']);
                    break;
                default:
                    $items[] = sprintf('%s#%s#%s', $rule['field'], $rule['operator'], $rule['threshold']);
                    break;
            }
        }
        // 进行排序，避免多个and条件直接顺序影响指标值的计算
        sort($items);

        return sha1($payload['taskid'] . implode('@', $items));
    }
}
