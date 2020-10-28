<?php

declare(strict_types=1);

namespace App\Consume\Message\Task;

use App\Model\AlarmTemplate;
use App\Support\ConditionArr;
use App\Support\SimpleCollection;

class Template extends SimpleCollection
{
    public function __construct($id, $name, $template, $type = AlarmTemplate::TYPE_DEFAULT)
    {
        $this->elements = [
            'id' => $id,
            'name' => $name,
            'template' => $template,
            'type' => $type,
        ];
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->elements['id'];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->elements['string'];
    }

    /**
     * @return array
     */
    public function getTemplate()
    {
        return $this->elements['template'];
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->elements['type'];
    }

    /**
     * 将模板默认值合并.
     *
     * @return array
     */
    public static function mergeTemplate(array $template)
    {
        $template = self::formatTemplate($template);
        $defaultTemplates = self::formatTemplate(config('dog-templates.tasks'));

        $merged = [];
        foreach (AlarmTemplate::$scenes as $scene => $sceneName) {
            foreach (AlarmTemplate::$channels as $channel) {
                $provideTpl = array_key_exists($scene, $template) && array_key_exists($channel, $template[$scene]) ?
                    $template[$scene][$channel] : [];
                $merged[$scene][$channel] = array_replace($defaultTemplates[$scene][$channel], $provideTpl);
            }
        }

        return $merged;
    }

    public static function formatTemplate($defaultTemplates)
    {
        $templates = [];
        foreach ($defaultTemplates as $scene => $channels) {
            foreach ($channels as $channel => $template) {
                if (! isset($template['vars_split'])) {
                    $template['vars_split'] = [];
                    foreach ($template['vars'] as $varName) {
                        $template['vars_split'][$varName] = ConditionArr::fieldSplit($varName);
                    }
                }
                $templates[$scene][$channel] = $template;
            }
        }
        return $templates;
    }
}
