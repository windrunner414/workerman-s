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

$tcpWorker->onConnect = function ($conn) {var_dump('on connect');};
$tcpWorker->onMessage = function ($conn, $data) {var_dump($data);};

$httpWorker = new Worker('http://0.0.0.0:5555');

$httpWorker->onMessage = function ($conn, $data) {var_dump($data);echo $_GET, PHP_EOL;};

Worker::runAll();