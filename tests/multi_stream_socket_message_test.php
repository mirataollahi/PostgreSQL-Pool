<?php

require __DIR__ . '/../vendor/autoload.php';
use App\Config;
use Josantonius\CliPrinter\CliPrinter;
use Swoole\Atomic;
use Swoole\Coroutine\WaitGroup;


const REQUEST_COUNT = 1000;


$cli = new CliPrinter();
$sentRequestCounter = new Atomic(0);
$receivedRequestCounter = new Atomic(0);
$isRunning = new WaitGroup();
$startTime = microtime(true);
for ($requestId = 1 ; $requestId <= REQUEST_COUNT ;$requestId++)
{
    \Swoole\Coroutine::create(function () use ($cli , $isRunning , $sentRequestCounter , $receivedRequestCounter) {
        $isRunning->add(1);
        $socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
        $socketPort = Config::get('SOCKET_SERVER_PORT' , '127.0.0.1');
        $sentRequestCounter->add(1);
        $context = stream_context_create();
        $socket = stream_socket_client("tcp://$socketHost:$socketPort", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            die("Error: $errstr ($errno)\n");
        }
        $cli->display('notice' , "Connected to $socketHost:$socketPort");
        $linkStatics = [
            'ua' => 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36' ,
            'client_ip' => '2.185.145.157' ,
            'url' => 'https://basalam.com/cart' ,
        ];
        $requestData = json_encode($linkStatics);

        fwrite($socket, $requestData);
        $response = fread($socket, 4096);
        if ($response)
            $receivedRequestCounter->add(1);

        $cli->display('notice' , "Server response: $response");
        fclose($socket);
        $isRunning->done();
    });
}
$isRunning->wait();
$duration = microtime(true) - $startTime;
echo PHP_EOL . PHP_EOL . PHP_EOL;
echo "*****************************" . PHP_EOL;
$cli->display('info' , "Sent Request : " . $sentRequestCounter->get() );
$cli->display('info' , "Received Request : " . $receivedRequestCounter->get() );
$cli->display('info' , "Duration Time : " . $duration );
