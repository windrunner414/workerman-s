<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/10/18
 * Time: 20:25 PM
 */

namespace Workerman\Events;

class SwooleEvent implements EventInterface
{
    protected $timer = [];

    function add($fd, $flag, $func, $args = [])
    {
        switch ($flag) {
            case self::EV_SIGNAL:
                return \swoole_process::signal($fd, $func);

            case self::EV_TIMER:
                $timerId = \swoole_timer_tick($fd * 1000, function () use ($func, $args) {
                    call_user_func_array($func, $args);
                });
                $this->timer[$timerId] = 1;
                return $timerId;

            case self::EV_TIMER_ONCE:
                $timerId = \swoole_timer_after($fd * 1000, function () use ($func, $args, &$timerId) {
                    call_user_func_array($func, $args);
                    unset($this->timer[$timerId]);
                });
                $this->timer[$timerId] = 1;
                return $timerId;

            case self::EV_READ:
                $hasRead = \swoole_event_isset($fd, SWOOLE_EVENT_READ);
                $hasWrite = \swoole_event_isset($fd, SWOOLE_EVENT_WRITE);

                if ($hasRead || $hasWrite) {
                    if (!$hasWrite) {
                        $eventFlag = SWOOLE_EVENT_READ;
                    } else {
                        $eventFlag = SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE;
                    }

                    return \swoole_event_set($fd, $func, null, $eventFlag);
                } else {
                    return \swoole_event_add($fd, $func, null, SWOOLE_EVENT_READ);
                }

            case self::EV_WRITE:
                $hasRead = \swoole_event_isset($fd, SWOOLE_EVENT_READ);
                $hasWrite = \swoole_event_isset($fd, SWOOLE_EVENT_WRITE);

                if ($hasRead || $hasWrite) {
                    if (!$hasRead) {
                        $eventFlag = SWOOLE_EVENT_WRITE;
                    } else {
                        $eventFlag = SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE;
                    }

                    return \swoole_event_set($fd, null, $func, $eventFlag);
                } else {
                    return \swoole_event_add($fd, null, $func, SWOOLE_EVENT_WRITE);
                }

            default:
                return false;
        }
    }

    function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_SIGNAL:
                return \swoole_process::signal($fd, null);

            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $res = \swoole_timer_clear($fd);
                if ($res) unset($this->timer[$fd]);
                return $res;

            case self::EV_READ:
                if (\swoole_event_isset($fd, SWOOLE_EVENT_WRITE)) {
                    return \swoole_event_set($fd, null, null, SWOOLE_EVENT_WRITE);
                } else {
                    return \swoole_event_del($fd);
                }

            case self::EV_WRITE:
                if (\swoole_event_isset($fd, SWOOLE_EVENT_READ)) {
                    return \swoole_event_set($fd, null, null, SWOOLE_EVENT_READ);
                } else {
                    return \swoole_event_del($fd);
                }

            default:
                return false;
        }
    }

    function clearAllTimer()
    {
        foreach ($this->timer as $id => $v) {
            \swoole_timer_clear($id);
        }

        $this->timer = [];
    }

    function loop()
    {
        \swoole_event_wait();
    }

    function destroy()
    {
        // \swoole_event_exit();
    }

    function getTimerCount()
    {
        return count($this->timer);
    }
}