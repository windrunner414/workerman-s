<?php
/**
 * Created by PhpStorm.
 * User: windrunner414
 * Date: 11/10/18
 * Time: 12:49 PM
 */

require '../Autoloader.php';

use \Workerman\Worker;
use \Workerman\Events\EventInterface;

$worker = new Worker('');

$worker->count = 4;

$worker->onWorkerStart = function ($worker) {
    $id = $worker->id;
    var_dump($id . 'start');
    Worker::$globalEvent->add(3, EventInterface::EV_TIMER_ONCE, function ($echo) {
        echo $echo;
        Worker::stopAll();
    }, ['stop' . PHP_EOL]);
};

$worker->onWorkerStop = function ($worker) {
    var_dump('worker stop, ' . $worker->id);
};

Worker::runAll();