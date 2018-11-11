<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/11/18
 * Time: 6:33 PM
 */

require '../Autoloader.php';

use Workerman\Worker;

$worker = new Worker('websocket://0.0.0.0:7777');
$worker->onMessage = function ($conn, $data) {$conn->send($data);};
Worker::runAll();