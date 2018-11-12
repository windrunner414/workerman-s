<?php

define('WORKERMAN_S', true);
define('WORKERMAN_S_VERSION', '0.1-alpha');

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Shanghai');
}

if (function_exists('opcache_reset')) {
    opcache_reset();
}

define('WORKERMAN_CONNECT_FAIL', 1);
define('WORKERMAN_SEND_FAIL', 2);

define('OS_TYPE_LINUX', 'linux');
define('OS_TYPE_WINDOWS', 'windows');

if(!class_exists('Error'))
{
    class Error extends Exception
    {
    }
}