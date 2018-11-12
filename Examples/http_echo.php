<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/10/18
 * Time: 12:42 PM
 */

require '../Autoloader.php';

use Workerman\Worker;

$worker = new Worker('http://0.0.0.0:5555');
$worker->count = 16;

$worker->onWorkerStart = function ($worker) {
    echo $worker->id, ' start' . PHP_EOL;
};

$worker->onMessage = function ($connection, $data) {
    //$connection->conn->end('hello world');
    //$connection->ended = true;
    \Workerman\Protocols\Http::header('Server: workerman-s');
    \Workerman\Protocols\Http::header('Server: workerman on swoole', true, 500);
    var_dump(\Workerman\Protocols\Http::header('', false, 404));
    var_dump(\Workerman\Protocols\Http::header('b', false, 200));
    \Workerman\Protocols\Http::header('My-Header: workerman-s');
    \Workerman\Protocols\Http::headerRemove('My-Header');
    $connection->send('hello world');
};

$worker->onClose = function ($connection) {
    var_dump('onClose');
};

Worker::runAll();