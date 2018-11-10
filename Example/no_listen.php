<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/10/18
 * Time: 12:49 PM
 */

require '../Autoloader.php';

use \Workerman\Worker;

$worker = new Worker('');

$worker->count = 4;

$worker->onWorkerStart = function ($worker) {
    $id = $worker->id;
    var_dump($id . 'start');
    swoole_timer_after(3000, function () {
        Worker::stopAll();
    });
};

$worker->onWorkerStop = function ($worker) {
    var_dump('worker stop, ' . $worker->id);
};

Worker::runAll();