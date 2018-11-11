<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/11/18
 * Time: 3:44 PM
 */

namespace Workerman\Lib;

class Session
{
    public static $path;
    public static $name;
    public static $gcProbability = 1;
    public static $gcDivisor = 1000;
    public static $gcMaxLifeTime = 1440;

    static function init()
    {
        self::$path = @session_save_path();
        if (!self::$path || strpos(self::$path, 'tcp://') === 0) {
            self::$path = sys_get_temp_dir();
        }

        self::$name = ini_get('session.name');

        if ($gc_probability = ini_get('session.gc_probability')) {
            self::$gcProbability = $gc_probability;
        }

        if ($gc_divisor = ini_get('session.gc_divisor')) {
            self::$gcDivisor = $gc_divisor;
        }

        if ($gc_max_life_time = ini_get('session.gc_maxlifetime')) {
            self::$gcMaxLifeTime = $gc_max_life_time;
        }

        if (!is_dir(self::$path)) mkdir(self::$path, 0777, true);
    }

    static function createId()
    {
        do {
            srand();
            $id = uniqid(rand(0, PHP_INT_MAX));
        } while (is_file(self::$path . '/sess_' . $id));

        return $id;
    }

    static function start($response)
    {
        if (isset($_COOKIE[self::$name])) return;

        $id = self::createId();
        file_put_contents(self::$path . '/sess_' . $id, '');
        $_COOKIE[self::$name] = $id;
        $response->cookie(self::$name, $id);
    }

    static function get()
    {
        if (!isset($_COOKIE[self::$name])) return [];

        $sessionId = $_COOKIE[self::$name];
        $file = self::$path . '/sess_' . $sessionId;

        if (is_file($file)) {
            $content = file_get_contents($file);
            if ($content !== false && $content !== '') {
                $session = \swoole_serialize::unpack($content);
                if ($session !== false) return $session;
            }
        }

        return [];
    }

    static function set($session)
    {
        if (!is_array($session)) return false;
        if (!isset($_COOKIE[self::$name])) return false;

        $sessionId = $_COOKIE[self::$name];
        $content = \swoole_serialize::pack($session);
        return file_put_contents(self::$path . '/sess_' . $sessionId, $content);
    }

    static function gc()
    {
        if (self::$gcDivisor > 0 && self::$gcProbability > 0 && self::$gcMaxLifeTime > 0) {
            if (rand(1, self::$gcDivisor) <= self::$gcProbability) self::_gc();
        }
    }

    static function _gc()
    {
        // TODO: Start a process to gc and prevent multi process gc at the same time (use swoole_table?)
        $now = time();

        foreach (glob(self::$path . '/sess_*') as $file) {
            if (is_file($file) && $now - filemtime($file) > self::$gcMaxLifeTime) {
                unlink($file);
            }
        }
    }
}

Session::init();