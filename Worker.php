<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/9/18
 * Time: 7:43 PM
 */

namespace Workerman;

class WorkerException extends \Exception
{

}

class Worker
{
    private static $workers = [];
    private static $workers_no_listen = [];

    private $host;
    private $port;
    private $type;
    private $socketType;
    private $protocol;

    /**
     * @var array fd => coroutineId
     */
    private $cidMapping = [];
    private $coroutine;

    public $setting = [];
    public $id;

    public $swooleObj = null;

    // event callback
    public $onWorkerStart = null;
    public $onWorkerStop = null;
    public $onWorkerReload = null;
    public $onConnect = null;
    public $onMessage = null;
    public $onClose = null;
    public $onBufferFull = null;
    public $onBufferDrain = null;
    public $onError = null;

    // setting
    public $count = 1;

    /**
     * Worker constructor.
     * @param string $listen
     * @param array $context
     * @throws WorkerException
     */
    function __construct($listen = '', $context = [])
    {
        $this->coroutine = class_exists('\\co');

        if ($listen !== '') {
            $set = [];

            // ignore ipv6_v6only, bindto, so_broadcast
            if (isset($context['backlog'])) $set['backlog'] = $context['backlog'];
            if (isset($context['so_reuseport'])) $set['enable_reuse_port'] = $context['so_reuseport'];
            if (isset($context['tcp_nodelay'])) $set['open_tcp_nodelay'] = $context['tcp_nodelay'];

            $bind = explode('://', $listen, 2);
            if (count($bind) !== 2) throw new WorkerException('listen param parse error');
            $type = $bind[0];

            if (strpos($type, 'unix') === 0) {
                $host = $bind[1];
                $port = 0;
            } else {
                $bind = explode(':', $bind[1], 2);
                if (count($bind) !== 2) throw new WorkerException('listen param parse error');
                $host = $bind[0];
                $port = $bind[1];
            }

            $this->host = $host;
            $this->port = $port;
            $this->type = $type;

            $this->setting = array_merge([
                'open_eof_check' => false,
                'open_length_check' => false,
                'open_http_protocol' => $type === 'http',
                'open_http2_protocol' => false,
                'open_websocket_protocol' => $type === 'websocket',
                'open_mqtt_protocol' => false,
                'open_websocket_close_frame' => false,
                'buffer_high_watermark' => 1048576,
                'buffer_low_watermark' => 0
            ], $set);

            $param = WorkerManager::add($host, $port, $type, $this);
            $this->socketType = $param['socketType'];
            $this->protocol = $param['protocol'];

            self::$workers[] = $this;
        } else {
            WorkerManager::addNoListen($this);
            self::$workers_no_listen[] = $this;
        }
    }

    static function trigger($worker, $event, ...$param)
    {
        if (isset($worker->$event) && is_callable($worker->$event)) {
            call_user_func($worker->$event, ...$param);
        }
    }

    static function workerStart($server, $workerId)
    {
        $_GET = new GlobalArray();
        $_POST = new GlobalArray();
        $_COOKIE = new GlobalArray();
        $_SESSION = new GlobalArray();
        $_SERVER = new GlobalArray();
        $_FILES = new GlobalArray();
        $_REQUEST = new GlobalArray();

        foreach (self::$workers as $worker) {
            $worker->id = $workerId;
            Worker::trigger($worker, 'onWorkerStart', $worker);
        }
    }

    static function workerStop($server, $workerId)
    {
        foreach (self::$workers as $worker) {
            Worker::trigger($worker, 'onWorkerStop', $worker);
        }
    }

    function _onProcessStart()
    {
        Worker::trigger($this, 'onWorkerStart', $this);
    }

    function _onProcessStop()
    {
        Worker::trigger($this, 'onWorkerStop', $this);
    }

    function _onConnect($server, $fd, $reactorId)
    {
        $connection = null;
        Worker::trigger($this, 'onConnect', $connection);
    }

    function _onReceive($server, $fd, $reactorId, $data)
    {
        //TODO: need unpack
        $connection = null;
        Worker::trigger($this, 'onMessage', $connection, $data);
    }

    /**
     * http server on receive
     *
     * @param $request
     * @param $response
     */
    function _onRequest($request, $response)
    {
        $this->initGlobalArray($request->fd, $request);

        //TODO:$_SESSION
        $connection = null;
        Worker::trigger($this, 'onMessage', $connection, $request->getData());

        $this->cleanGlobalArray($request->fd);
    }

    /**
     * websocket server on open (after handshake)
     *
     * @param $server
     * @param $request
     */
    function _onOpen($server, $request)
    {
        $this->initGlobalArray($request->fd, $request);
    }

    /**
     * websocket server on receive
     *
     * @param $server
     * @param $frame
     */
    function _onMessage($server, $frame)
    {
        $this->setGlobalArrayId($frame->fd);

        $connection = null;
        Worker::trigger($this, 'onMessage', $connection, $frame->data);
    }

    function _onPacket($server, $data, $clientInfo)
    {
        //TODO: need unpack
        $connection = null;
        Worker::trigger($this, 'onMessage', $connection, $data);
    }

    function _onClose($server, $fd, $reactorId)
    {
        $connection = null;
        Worker::trigger($this, 'onClose', $connection);

        if ($this->type === 'websocket') {
            $this->cleanGlobalArray($fd);
        }
    }

    function _onBufferFull($server, $fd)
    {
        $connection = null;
        Worker::trigger($this, 'onBufferFull', $connection);
    }

    function _onBufferEmpty($server, $fd)
    {
        $connection = null;
        Worker::trigger($this, 'onBufferDrain', $connection);
    }

    function setGlobalArrayId($id)
    {
        if ($this->coroutine) {
            $cid = \co::getuid();

            if (isset($this->cidMapping[$id]) && $this->cidMapping[$id] !== $cid) {
                $_SERVER->move($this->cidMapping[$id]);
                $_GET->move($this->cidMapping[$id]);
                $_POST->move($this->cidMapping[$id]);
                $_REQUEST->move($this->cidMapping[$id]);
                $_COOKIE->move($this->cidMapping[$id]);
                $_FILES->move($this->cidMapping[$id]);
                $_SESSION->move($this->cidMapping[$id]);
            }

            $this->cidMapping[$id] = $cid;
            return;
        }

        $_SERVER->setId($id);
        $_GET->setId($id);
        $_POST->setId($id);
        $_REQUEST->setId($id);
        $_COOKIE->setId($id);
        $_FILES->setId($id);
        $_SESSION->setId($id);
    }

    function initGlobalArray($id, $request)
    {
        $this->setGlobalArrayId($id);

        if (!$request->get) $request->get = [];
        if (!$request->post) $request->post = [];
        if (!$request->cookie) $request->cookie = [];
        if (!$request->files) $request->files = [];
        if (!$request->server) $request->server = [];

        $_SERVER->assign(array_change_key_case($request->server, CASE_UPPER));
        $_GET->assign($request->get);
        $_POST->assign($request->post);
        $_REQUEST->assign(array_merge($request->get, $request->post));
        $_COOKIE->assign($request->cookie);
        $_FILES->assign($request->files);
        $_SESSION->assign([]); //TODO
    }

    function cleanGlobalArray($id)
    {
        $this->setGlobalArrayId($id);

        $_SERVER->remove();
        $_GET->remove();
        $_POST->remove();
        $_REQUEST->remove();
        $_COOKIE->remove();
        $_FILES->remove();
        $_SESSION->remove();

        if ($this->coroutine) {
            unset($this->cidMapping[$id]);
        }
    }

    /**
     * @throws WorkerException
     */
    static function runAll()
    {
        WorkerManager::runAll();
    }

    static function stopAll()
    {
        $mainWorker = WorkerManager::getMainWorker();

        if ($mainWorker) {
            $mainWorker->stop();
        } else {
            exit(0);
        }
    }
}