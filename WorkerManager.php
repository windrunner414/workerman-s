<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/9/18
 * Time: 7:44 PM
 */

namespace Workerman;

/**
 * Class WorkerManager
 * This class manage swoole_server and swoole_server_port objects
 *
 * @package Workerman
 */
class WorkerManager
{
    /**
     * worker types
     */
    const TYPE = [
        'tcp'         => SWOOLE_SOCK_TCP,
        'tcp6'        => SWOOLE_SOCK_TCP6,
        'udp'         => SWOOLE_SOCK_UDP,
        'udp6'        => SWOOLE_SOCK_UDP6,
        'unix_dgram'  => SWOOLE_SOCK_UNIX_DGRAM,
        'unix_stream' => SWOOLE_SOCK_UNIX_STREAM,
        'unix'        => SWOOLE_SOCK_UNIX_STREAM,
        'http'        => SWOOLE_SOCK_TCP,
        'websocket'   => SWOOLE_SOCK_TCP
    ];

    /**
     * stream type
     */
    const STREAM_TYPE = [SWOOLE_SOCK_TCP, SWOOLE_SOCK_TCP6, SWOOLE_SOCK_UNIX_STREAM];

    /**
     * @var array worker added but not created
     */
    private static $workers_wait = [];
    private static $workers_no_listen_wait = [];

    /**
     * @var array worker list
     */
    private static $workers = [];
    private static $workers_no_listen = [];

    /**
     * @var array if no worker needs to listen a port, will create process without swoole_server
     */
    private static $workers_pid = [];
    private static $process_worker = [];
    private static $process_running = false;

    /**
     * get swoole_server object
     *
     * @return mixed|null
     */
    static function getMainWorker()
    {
        return self::getWorker(0);
    }

    /**
     * get a worker object
     *
     * @param $workerId
     * @return mixed|null
     */
    static function getWorker($workerId)
    {
        return isset(self::$workers[$workerId]) ? self::$workers[$workerId] : null;
    }

    /**
     * add a worker
     *
     * @param $host
     * @param $port
     * @param $type
     * @param $object
     * @return array
     */
    static function add($host, $port, $type, $object)
    {
        $protocol = null;

        if (!isset(self::TYPE[$type])) {
            $protocol = '\\Protocols\\' . $type;
            if (!class_exists($protocol)) {
                $protocol = '\\Workerman\\Protocols\\' . $type;
                if (!class_exists($protocol)) {
                    exit('Unknown protocol ' . $type . PHP_EOL);
                }
            }

            if (strpos($host, ':') !== false) {
                $socketType = SWOOLE_SOCK_TCP6;
            } else {
                $socketType = SWOOLE_SOCK_TCP;
            }
        } else {
            $socketType = self::TYPE[$type];

            if (strpos($host, ':') !== false) {
                if ($socketType === SWOOLE_SOCK_TCP) $socketType = SWOOLE_SOCK_TCP6;
                if ($socketType === SWOOLE_SOCK_UDP) $socketType = SWOOLE_SOCK_UDP6;
            }
        }

        $param = ['host' => $host, 'port' => $port, 'type' => $type, 'socketType' => $socketType, 'protocol' => $protocol, 'object' => $object];

        if (in_array($type, ['http', 'websocket'], true)) {
            array_unshift(self::$workers_wait, $param);
        } else {
            array_push(self::$workers_wait, $param);
        }

        return $param;
    }

    /**
     * @param $worker
     */
    static function addNoListen($worker)
    {
        self::$workers_no_listen_wait[] = $worker;
    }

    /**
     * create workers
     *
     * @throws WorkerException
     */
    private static function create()
    {
        foreach (self::$workers_wait as $v) {
            if (empty(self::$workers)) {
                if ($v['type'] === 'http') {
                    $worker = new \swoole_http_server($v['host'], $v['port']);
                } else if ($v['type'] === 'websocket') {
                    $worker = new \swoole_websocket_server($v['host'], $v['port']);
                } else {
                    $worker = new \swoole_server($v['host'], $v['port'], SWOOLE_PROCESS, $v['socketType']);
                }

                $worker->on('workerStart', ['\\Workerman\\Worker', 'workerStart']);
                $worker->on('workerStop', ['\\Workerman\\Worker', 'workerStop']);

                $workerSetting = [
                    'worker_num' => $v['object']->count
                ];
            } else {
                $worker = self::getMainWorker()->addListener($v['host'], $v['port'], $v['socketType']);
                $workerSetting = [];
            }

            if (!$worker) throw new WorkerException("Worker can't be created, {$v['type']}://{$v['host']}:{$v['port']}");

            // workerman setting
            $v['object']->setting = array_merge($v['object']->setting, $workerSetting);
            $worker->set($v['object']->setting);

            if (in_array($v['socketType'], self::STREAM_TYPE, true)) {
                $worker->on('connect', [$v['object'], '_onConnect']);

                if ($v['type'] === 'http') {
                    $worker->on('request', [$v['object'], '_onRequest']);
                } else if ($v['type'] === 'websocket') {
                    $worker->on('message', [$v['object'], '_onMessage']);
                    $worker->on('open', [$v['object'], '_onOpen']);
                } else {
                    $worker->on('receive', [$v['object'], '_onReceive']);
                }

                $worker->on('close', [$v['object'], '_onClose']);
                $worker->on('bufferFull', [$v['object'], '_onBufferFull']);
                $worker->on('bufferEmpty', [$v['object'], '_onBufferEmpty']);
            } else {
                $worker->on('packet', [$v['object'], '_onPacket']);
            }

            self::$workers[] = $worker;
            $v['object']->swooleObj = $worker;
        }

        self::$workers_wait = [];

        self::createProcess();
        foreach (self::$workers_no_listen as $worker) {
            if (!self::getMainWorker()->addProcess($worker)) {
                throw new WorkerException('addProcess failed');
            }
        }

        self::getMainWorker()->set(['dispatch_mode' => 2]);
    }

    private static function _createProcess($v, $id)
    {
        return new \swoole_process(function ($process) use ($v, $id) {
            $v->id = $id;
            $v->swooleObj = $process;

            register_shutdown_function([$v, '_onProcessStop']);
            call_user_func([$v, '_onProcessStart']);
            \swoole_event_wait();
        });
    }

    /**
     * create workers without listen
     */
    static function createProcess()
    {
        foreach (self::$workers_no_listen_wait as $v) {
            foreach (range(0, $v->count - 1) as $id) {
                $process = self::_createProcess($v, $id);

                $v->swooleObj = $process;
                self::$workers_no_listen[] = $process;
                self::$process_worker[] = [$v, $id];
            }
        }

        self::$workers_no_listen_wait = [];
    }

    private static function setSignal()
    {
        \swoole_process::signal(SIGCHLD, function ($sig) {
            while ($ret = \swoole_process::wait(false)) {
                if (!self::$process_running) {
                    unset(self::$workers_pid[$ret['pid']]);
                    continue;
                }

                $k = self::$workers_pid[$ret['pid']];
                unset(self::$workers_pid[$ret['pid']]);

                $v = self::$process_worker[$k][0];
                $id = self::$process_worker[$k][1];
                $process = self::_createProcess($v, $id);
                $pid = $process->start();

                if ($pid !== false) {
                    self::$workers_pid[$pid] = $k;
                    self::$workers_no_listen[$k] = $process;
                    self::$process_worker[$k][0]->swooleObj = $process;
                }
            }

            if (!self::$process_running && empty(self::$workers_pid)) exit(0);
        });

        \swoole_process::signal(SIGTERM, function ($sig) {
            self::$process_running = false;

            foreach (self::$workers_pid as $pid => $v) {
                \swoole_process::kill($pid);
            }
        });
    }

    /**
     * run all workers
     *
     * @throws WorkerException
     */
    static function runAll()
    {
        self::$process_running = true;

        if (empty(self::$workers_wait)) {
            self::setSignal();
            self::createProcess();

            foreach (self::$workers_no_listen as $k => $worker) {
                $pid = $worker->start();
                if ($pid === false) throw new WorkerException('Can\'t start process');

                self::$workers_pid[$pid] = $k;
            }

            \swoole_event_wait();
        } else {
            self::create();
            self::getMainWorker()->start();
        }
    }
}