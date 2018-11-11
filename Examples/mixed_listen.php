<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/10/18
 * Time: 1:09 PM
 */

require '../Autoloader.php';

use \Workerman\Worker;

$tcpWorker = new Worker('tcp://0.0.0.0:4444');
$tcpWorker->count = 8; // This will not take effect

$tcpWorker->onConnect = function ($conn) use ($tcpWorker) {
    // var_dump('on connect');
    var_dump($tcpWorker->connections);
};
$tcpWorker->onMessage = function ($conn, $data) {var_dump($data);};
$tcpWorker->onWorkerStart = function ($worker) {
    // var_dump('tcp ' . $worker->id . ' start');
    // var_dump(get_class($worker->swooleObj)); // swoole_server_port
};

$websocket = new Worker('websocket://0.0.0.0:7777');
$websocket->count = 4; // because after this worker, create a http worker, so this setting will not take effect
$websocket->name = 'websocket'; // will use the last worker name, so it will be workerman-s

$websocket->onConnect = function ($conn) {
    var_dump('websocket connect');
};
$websocket->onWebSocketConnect = function ($conn, $data) use ($websocket) {
    var_dump($data);
};
$websocket->onMessage = function ($conn, $data) use ($websocket) {
    var_dump($data);
};

$httpWorker = new Worker('http://0.0.0.0:5555');
$httpWorker->count = 1; // right
$httpWorker->name = 'workerman-s';

$httpWorker->onMessage = function ($conn, $data) use ($httpWorker) {
    // var_dump($data);
    // echo $_GET, PHP_EOL;
    var_dump($httpWorker->connections);
};
$httpWorker->onWorkerStart = function ($worker) {
    // var_dump('http ' . $worker->id . ' start');
    // var_dump(get_class($worker->swooleObj)); // swoole_http_server
    // if ($worker->id === 0) var_dump('main worker ' . get_class(\Workerman\WorkerManager::getMainWorker()));
};

$taskWorker = new Worker('');
$taskWorker->count = 8; // will create 4 process, not use server proccess
$taskWorker->name = 'taskWorker';

$taskWorker->onWorkerStart = function ($worker) {
    $id = $worker->id;
    // var_dump($id . 'start');
    swoole_timer_after(3000, function () {
        Worker::stopAll();
    });
};

$taskWorker->onWorkerStop = function ($worker) {
    // var_dump('worker stop, ' . $worker->id);
};

Worker::runAll();