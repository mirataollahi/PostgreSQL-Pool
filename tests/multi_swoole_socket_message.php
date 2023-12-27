<?php
const BASE_PATH = __DIR__ . '/../';
use App\Core\Config;
use Josantonius\CliPrinter\CliPrinter;
use Swoole\Atomic;
use Swoole\Coroutine\WaitGroup;

require __DIR__ . "/../vendor/autoload.php";

const REQUEST_COUNT = 100;

$cli = new CliPrinter();


\Swoole\Coroutine::create(function () use ($cli){

    $cli = new CliPrinter();
    $isRunning = new WaitGroup();
    $sentRequestCounter = new Atomic(0);
    $receivedRequestCounter = new Atomic(0);
    $startTime = microtime(true);

    for ($requestId = 1 ; $requestId <= REQUEST_COUNT ;$requestId++)
    {
        \Swoole\Coroutine::create(function () use ($isRunning , $sentRequestCounter , $receivedRequestCounter , $cli) {
            $isRunning->add(1);
            $socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
            $socketPort = Config::get('SOCKET_SERVER_PORT' , 8100);

            $client = new Swoole\Client(SWOOLE_SOCK_TCP);
            if (!$client->connect($socketHost, $socketPort)) {
                echo "Connection failed. Error: {$client->errCode}\n";
                return;
            }

            else {
                $cli->display('notice' , "Connected to $socketHost:$socketPort");

                $linkStatics = [
                    'ua' => 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36' ,
                    'client_ip' => '2.185.145.157' ,
                    'url' => 'https://basalam.com/cart' ,
                ];
                $sentRequestCounter->add(1);
                $requestData = json_encode($linkStatics);
                $client->send($requestData);
                $cli->display('notice' , "Server response");
                $receivedRequestCounter->add(1);
                \Swoole\Coroutine::sleep(1);
                $client->close();
                $isRunning->done();
                \Swoole\Coroutine::sleep(0.09);
            }

        });
    }
    $isRunning->wait();
    $duration = microtime(true) - $startTime;
    echo PHP_EOL . PHP_EOL . PHP_EOL;
    echo "*****************************" . PHP_EOL;
    $cli->display('info' , "Sent Request : " . $sentRequestCounter->get() );
    $cli->display('info' , "Received Request : " . $receivedRequestCounter->get() );
    $cli->display('info' , "Duration Time : " . $duration );

});