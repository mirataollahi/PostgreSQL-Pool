<?php


require __DIR__ . "/../vendor/autoload.php";


const TCP_HOST = '127.0.0.1';
const TCP_PORT = 8100;


\Swoole\Coroutine::create(function () {

    // Create a TCP socket client
    $client = new Swoole\Client(SWOOLE_SOCK_TCP);

    if (!$client->connect(TCP_HOST, TCP_PORT)) {
        echo "Connection failed. Error: {$client->errCode}\n";
        exit();
    }

    $message = "Hello, Swoole TCP Server!";
    $client->send($message);


    sleep(2);
    $client->close();

});