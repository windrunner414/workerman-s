<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/11/18
 * Time: 7:01 PM
 */

namespace Workerman\Connection;

use Workerman\Worker;
use Workerman\WorkerManager;

class TcpConnection extends ConnectionInterface
{
    const TYPE_HTTP = 0;
    const TYPE_WEBSOCKET = 1;
    const TYPE_OTHER = 2;

    const EVENTS = ['onMessage', 'onClose', 'onBufferFull', 'onBufferDrain', 'onError', 'onWebSocketConnect', 'onWebSocketClose'];

    private $destroyed = false;
    private $fd;

    public static $connections = [];
    public static $defaultMaxSendBufferSize = 1048576;
    public static $maxPackageSize = 10485760;

    public $id;
    public $conn = null;
    public $type;
    public $closed = false;
    /**
     * @var bool if not conn->end() on a request, will auto end
     */
    public $ended = false;
    /**
     * @var string http post raw data
     */
    public $rawPostData = '';

    public $protocol;
    public $transport;
    public $worker;

    public $maxSendBufferSize = 1048576; // will ignore it

    // event callback
    public $onMessage = null;
    public $onClose = null;
    public $onBufferFull = null;
    public $onBufferDrain = null;
    public $onError = null;
    public $onWebSocketConnect = null;
    public $onWebSocketClose = null;

    function __construct($type, $fd, $protocol, $worker, $ready = true)
    {
        $this->type = $type;
        $this->id = $fd;
        $this->fd = $fd;
        $this->protocol = $protocol;
        $this->transport = $worker->transport;
        $this->worker = $worker;
        if ($ready) $this->conn = $fd;

        self::$connections[$fd] = $this;
        ++self::$statistics['connection_count'];
    }

    function __destruct()
    {
        if (!$this->destroyed) $this->_destroy();
    }

    function _destroy()
    {
        if ($this->destroyed) return;
        $this->destroyed = true;

        if (!$this->closed) try {$this->close();} catch (\Exception $e) {}
        unset(self::$connections[$this->fd]);
        --self::$statistics['connection_count'];
    }

    function __call($name, $arguments)
    {
        if (method_exists($this->protocol, $name)) {
            return call_user_func([$this->protocol, $name], $this, $arguments);
        } else {
            return false;
        }
    }

    /**
     * @return bool
     * @throws \Exception
     */
    function check()
    {
        if ($this->conn === null) throw new \Exception('http or websocket connection can\'t send data before onMessage');

        return !$this->closed;
    }

    /**
     * @param string $send_buffer
     * @param bool $raw
     * @return bool
     * @throws \Exception
     */
    function send($send_buffer, $raw = false)
    {
        if (!$this->check()) goto send_fail;

        switch ($this->type) {
            case self::TYPE_HTTP:
                if (!$this->conn->write($send_buffer)) goto send_fail;
                break;

            case self::TYPE_WEBSOCKET:
                if (!$raw) $send_buffer = \swoole_websocket_server::pack($send_buffer);
                if (!WorkerManager::getMainWorker()->send($this->fd, $send_buffer)) goto send_fail;
                break;

            case self::TYPE_OTHER:
                if ($this->protocol && !$raw) $send_buffer = ($this->protocol)::encode($send_buffer);
                if (!WorkerManager::getMainWorker()->send($this->fd, $send_buffer)) goto send_fail;
                break;

            default:
                throw new \Exception('Unknown connection type');
        }

        return true;

        send_fail:
        ++TcpConnection::$statistics['send_fail'];

        if ($this->closed) $msg = 'client closed';
        else $msg = 'send buffer full and drop package';

        Worker::trigger($this, 'onError', $this, WORKERMAN_SEND_FAIL, $msg);
        return false;
    }

    function getInfo()
    {
        return WorkerManager::getMainWorker()->getClientInfo($this->fd, 0, true);
    }

    function getRemoteIp()
    {
        $info = $this->getInfo();
        if (!$info) return '';
        return $info['remote_ip'];
    }

    function getRemotePort()
    {
        $info = $this->getInfo();
        if (!$info) return '';
        return $info['remote_port'];
    }

    function getRemoteAddress()
    {
        $ip = $this->getRemoteIp();
        $port = $this->getRemotePort();

        if (strpos($ip, ':') !== false) return "[{$ip}]:{$port}";
        return $ip . ':' . $port;
    }

    function getLocalIp()
    {
        return $this->worker->host;
    }

    function getLocalPort()
    {
        return $this->worker->port;
    }

    function getLocalAddress()
    {
        $ip = $this->getLocalIp();
        $port = $this->getLocalPort();

        if (strpos($ip, ':') !== false) return "[{$ip}]:{$port}";
        return $ip . ':' . $port;
    }

    function isIPv6()
    {
        return strpos($this->getRemoteIp(), ':') !== false;
    }

    function isIPv4()
    {
        return strpos($this->getRemoteIp(), ':') === false;
    }

    /**
     * @param string $data
     * @throws \Exception
     */
    function close($data = '')
    {
        if ($this->closed) return;
        if ($data !== '') $this->send($data);

        switch ($this->type) {
            case self::TYPE_HTTP:
                $this->conn->end();
                $this->ended = true;
                break;

            case self::TYPE_WEBSOCKET:
                $this->send(\swoole_websocket_server::pack('', 8), true);
                WorkerManager::getMainWorker()->close($this->fd);
                break;

            case self::TYPE_OTHER:
                WorkerManager::getMainWorker()->close($this->fd);
                break;

            default:
                throw new \Exception('Unknown connection type');
        }
    }

    function destroy()
    {
        WorkerManager::getMainWorker()->close($this->fd, true);
        if ($this->type === self::TYPE_HTTP) $this->ended = true;
    }

    function pauseRecv()
    {

    }

    function resumeRecv()
    {

    }

    function pipe()
    {

    }
}