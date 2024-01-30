<?php
const BASE_PATH = __DIR__ . '/../';
use Josantonius\CliPrinter\CliPrinter;
require __DIR__ . "/../vendor/autoload.php";
ini_set('display_errors', true);


const QUERY_COUNT = 1;


$cli = new CliPrinter();
\Swoole\Coroutine::create(function () {

    $requestData = [
        'os' => 'Android',
        'os_version' => '10',
        'browser' => 'Chrome Mobile',
        'browser_version' => '112.0.0',
        'client_ip' => '2.185.145.157',
        'base_url' => 'basalam.com',
        'url_path' => 'shopping',
        'full_url' => 'https://basalam.com/cart',
        'created_at' => (new DateTime('now'))->format('Y-m-d H:i:s') ,
    ];
    $databaseManager = new \App\Database\ConnectionPool();
    for ($queryId = 1 ; $queryId <= 2 ; $queryId ++){
        \Swoole\Coroutine::create(function () use ($requestData , $databaseManager){
            try {
               $databaseManager->saveLinkStatics($requestData);
            } catch (PDOException $e) {
                echo "Error: " . $e->getMessage() . PHP_EOL;
            }
        });
    }
});

