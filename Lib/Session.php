<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/11/18
 * Time: 3:44 PM
 */

namespace Workerman\Lib;

use Workerman\Protocols\Http;

class Session
{
    public static $path;
    public static $name;
    public static $gcProbability = 1;
    public static $gcDivisor = 1000;
    public static $gcMaxLifeTime = 1440;
    public static $httpOnly = true;

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

        self::$httpOnly = ini_get('session.cookie_httponly') ? true : false;

        if (!is_dir(self::$path)) mkdir(self::$path, 0777, true);
    }

    static function sessionFile($id = null)
    {
        if (!$id) $id = self::sessionId();
        if (!$id) return '';
        return self::$path . '/sess_' . $id;
    }

    static function createId()
    {
        do {
            srand();
            $id = uniqid(rand(0, PHP_INT_MAX));
        } while (is_file(self::sessionFile($id)));

        return $id;
    }

    static function sessionId()
    {
        if (!self::sessionStarted()) return '';
        if (!isset($GLOBALS[Http::$globalName]['session_id'])) return '';
        return $GLOBALS[Http::$globalName]['session_id'];
    }

    static function sessionName($name = null)
    {
        if (!self::sessionStarted() && $name) self::$name = $name;
        return self::$name;
    }

    static function sessionSavePath($path = null)
    {
        if (!self::sessionStarted() && $path) self::$path = $path;
        return self::$path;
    }

    static function sessionStarted()
    {
        if (!isset($GLOBALS[Http::$globalName]['session_started'])) return false;
        return $GLOBALS[Http::$globalName]['session_started'];
    }

    static function start($response)
    {
        if (self::sessionStarted()) return false;
        $GLOBALS[Http::$globalName]['session_started'] = true;

        if (isset($_COOKIE[self::$name])) {
            $id = $_COOKIE[self::$name];
        } else {
            $id = self::createId();
            file_put_contents(self::sessionFile($id), '');
            $response->cookie(self::$name, $id, 0, '/', '', false, self::$httpOnly);
        }

        $GLOBALS[Http::$globalName]['session_id'] = $id;
        self::gc();

        $_SESSION->assign(self::get());
        return true;
    }

    static function get()
    {
        $id = self::sessionId();
        if (!$id) return [];
        $file = self::sessionFile($id);

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

        $id = self::sessionId();
        if (!$id) return false;

        $content = \swoole_serialize::pack($session);
        return file_put_contents(self::sessionFile($id), $content);
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