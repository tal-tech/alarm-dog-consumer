<?php

declare(strict_types=1);

namespace App\Support;

use ArrayAccess;

class SimpleCollection implements ArrayAccess
{
    /**
     * @var array
     */
    protected $elements = [];

    /**
     * @param array $elements
     */
    public function __construct(?array $elements = [])
    {
        if (is_array($elements)) {
            $this->elements = $elements;
        }
    }

    public function replace(array $elements)
    {
        $this->elements = $elements;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->elements;
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return isset($this->elements[$key]) || array_key_exists($key, $this->elements);
    }

    /**
     * @param mixed $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->elements[$key] ?? null;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function offsetSet($key, $value)
    {
        $this->elements[$key] = $value;
    }

    /**
     * @param mixed $key
     */
    public function offsetUnset($key)
    {
        if (! $this->offsetExists($key)) {
            unset($this->elements[$key]);
        }
    }
}
