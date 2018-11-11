<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/9/18
 * Time: 10:25 PM
 */

namespace Workerman;

class GlobalArray implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable
{
    private $context = [];
    private $co;
    private $id;

    function __construct()
    {
        $this->co = class_exists('\\co');
    }

    function &get()
    {
        $id = $this->getId();

        if (!isset($this->context[$id])) {
            $empty = [];
            return $empty;
        }

        return $this->context[$id];
    }

    function set($key, $data)
    {
        $id = $this->getId();

        if (!isset($this->context[$id])) {
            $this->context[$id] = [$key => $data];
            return;
        }

        $this->context[$id][$key] = $data;
    }

    function del($key)
    {
        unset($this->context[$this->getId()][$key]);
    }

    function assign($data)
    {
        $this->context[$this->getId()] = $data;
    }

    function remove()
    {
        unset($this->context[$this->getId()]);
    }

    function move($oldId)
    {
        $id = $this->getId();

        $this->context[$id] = $this->context[$oldId];
        unset($this->context[$oldId]);
    }

    function getId()
    {
        if ($this->co) {
            return \co::getuid();
        } else {
            return $this->id;
        }
    }

    function setId($id)
    {
        $this->id = $id;
    }

    function offsetExists($offset)
    {
        return isset(($this->get())[$offset]);
    }

    function &offsetGet($offset)
    {
        $arr = &$this->get();
        return $arr[$offset];
    }

    function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    function offsetUnset($offset)
    {
        $this->del($offset);
    }

    function current()
    {
        $id = $this->getId();

        if (isset($this->context[$id])) {
            return current($this->context[$id]);
        } else {
            return false;
        }
    }

    function key()
    {
        $id = $this->getId();

        if (isset($this->context[$id])) {
            return key($this->context[$id]);
        } else {
            return null;
        }
    }

    function next()
    {
        $id = $this->getId();

        if (isset($this->context[$id])) {
            next($this->context[$id]);
        }
    }

    function rewind()
    {
        $id = $this->getId();

        if (isset($this->context[$id])) {
            reset($this->context[$id]);
        }
    }

    function valid()
    {
        $id = $this->getId();

        if (isset($this->context[$id])) {
            return key($this->context[$id]) !== null;
        } else {
            return false;
        }
    }

    function count()
    {
        return count($this->get());
    }

    function jsonSerialize()
    {
        return $this->get();
    }

    function __toString()
    {
        return (string)var_export($this->get(), true);
    }
}