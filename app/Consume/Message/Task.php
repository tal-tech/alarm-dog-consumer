<?php

declare(strict_types=1);

namespace App\Consume\Message;

use App\Consume\Message\Task\Compress;
use App\Consume\Message\Task\Filter;
use App\Consume\Message\Task\Receiver;
use App\Consume\Message\Task\Recovery;
use App\Consume\Message\Task\Template;
use App\Consume\Message\Task\Upgrade;
use App\Consume\Message\Task\Workflow;
use App\Support\SimpleCollection;

class Task extends SimpleCollection
{
    /**
     * @return Receiver
     */
    public function getReceiver()
    {
        return $this->elements['receiver'];
    }

    /**
     * @return Workflow
     */
    public function getWorkflow()
    {
        return $this->elements['workflow'];
    }

    /**
     * @return Filter
     */
    public function getFilter()
    {
        return $this->elements['filter'];
    }

    /**
     * @return Compress
     */
    public function getCompress()
    {
        return $this->elements['compress'];
    }

    /**
     * @return Upgrade
     */
    public function getUpgrade()
    {
        return $this->elements['upgrade'];
    }

    /**
     * @return Recovery
     */
    public function getRecovery()
    {
        return $this->elements['recovery'];
    }

    /**
     * @return Template
     */
    public function getTemplate()
    {
        return $this->elements['template'];
    }

    /**
     * @return bool
     */
    public function isEnableWorkflow()
    {
        return (bool) $this->elements['enable_workflow'];
    }

    /**
     * @return bool
     */
    public function isEnableFilter()
    {
        return (bool) $this->elements['enable_filter'];
    }

    /**
     * @return bool
     */
    public function isEnableCompress()
    {
        return (bool) $this->elements['enable_compress'];
    }

    /**
     * @return bool
     */
    public function isEnableUpgrade()
    {
        return (bool) $this->elements['enable_upgrade'];
    }

    /**
     * @return bool
     */
    public function isEnableRecovery()
    {
        return (bool) $this->elements['recovery'];
    }

    /**
     * @return bool
     */
    public function isSaveDb()
    {
        return (bool) $this->elements['flag_save_db'];
    }

    public function isRunning()
    {
        return $this->elements['status'] == 1;
    }
}
