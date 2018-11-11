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
    public static $gcProbability;
    public static $gcDivisor;
    public static $gcMaxLifeTime;

    static function init()
    {
        self::$path = @session_save_path();
        if (!self::$path || strpos(self::$path, 'tcp://') === 0) {
            self::$path = sys_get_temp_dir();
        }

        self::$name = ini_get('session.name');

    }
}

Session::init();