<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/12/18
 * Time: 5:02 PM
 */

namespace Workerman\Protocols;

use Workerman\Connection\ConnectionInterface;
use Workerman\Lib\Session;

/**
 * Compatible with workerman, Http::setcookie...
 * @package Workerman\Protocols
 */
class Http implements ProtocolInterface
{
    public static $globalName = 'WORKERMAN_S_HTTP_RESPONSE_INFO';

    static function encode($data, ConnectionInterface $connection)
    {

    }
    
    static function decode($recv_buffer, ConnectionInterface $connection)
    {

    }
    
    static function input($recv_buffer, ConnectionInterface $connection)
    {

    }

    static function getResponse()
    {
        if (!isset($GLOBALS[self::$globalName]['swoole_http_response'])) return null;
        return $GLOBALS[self::$globalName]['swoole_http_response'];
    }

    static function header($content, $replace = true, $http_response_code = 0)
    {
        $response = self::getResponse();
        if (!$response) return false;

        if ($http_response_code) $response->status($http_response_code);
        if ($content === '') return true;
        $header = explode(':', $content, 2);
        if (count($header) !== 2) return false;

        $header[0] = strtolower($header[0]);
        $header[1] = trim($header[1]);
        if (isset($GLOBALS[self::$globalName]['header'][$header[0]]) && !$replace) return false;
        $GLOBALS[self::$globalName]['header'][$header[0]] = $header[1];

        $response->header($header[0], $header[1]);
        return true;
    }

    static function headerRemove($name)
    {
        $response = self::getResponse();
        if (!$response) return;

        $name = strtolower($name);
        unset($GLOBALS[self::$globalName]['header'][$name]);
        $response->header($name, null);
    }

    static function setcookie(
        $name,
        $value = '',
        $maxAge = 0,
        $path = '/',
        $domain = '',
        $secure = false,
        $HTTPOnly = false
    )
    {
        $response = self::getResponse();
        if (!$response) return false;

        $response->cookie($name, $value, empty($maxAge) ? 0 : time() + $maxAge, $path, $domain, $secure, $HTTPOnly);
        return true;
    }

    static function sessionCreateId()
    {
        return Session::createId();
    }

    static function sessionId($id = null)
    {
        return Session::sessionId();
    }

    static function sessionName($name = null)
    {
        return Session::sessionName($name);
    }

    static function sessionSavePath($path = null)
    {
        return Session::sessionSavePath($path);
    }

    static function sessionStarted()
    {
        return Session::sessionStarted();
    }

    static function sessionStart()
    {
        $response = self::getResponse();
        if (!$response) return false;

        return Session::start($response);
    }

    static function sessionWriteClose()
    {
        return Session::set($_SESSION->get());
    }

    /**
     * @param string $msg
     * @throws \Exception
     */
    static function end($msg = '')
    {
        if ($msg) echo $msg;

        throw new \Exception('jump_exit');
    }

    static function tryGcSessions()
    {
        Session::gc();
    }
}