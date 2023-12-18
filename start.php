<?php

require __DIR__ . '/vendor/autoload.php';

/*
 * Config base swoole coroutines
 */
Swoole\Coroutine::set([
    'max_coroutine' => 1000000,
    'aio_worker_num' => 2
]);



use App\RequestSender;



\Swoole\Coroutine::create(function (){

    $requestSender = RequestSender::create()
        ->setHost(host: "api.amir")
        ->requestCount(10000)
        ->setUri('/admin/auth/login')
        ->setPoolConfig(poolSize: 100 , connectionCount: 90)
        ->start();

});

