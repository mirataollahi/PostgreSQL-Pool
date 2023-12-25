<?php


use App\PostgresConnectionManager;
use Josantonius\CliPrinter\CliPrinter;

require __DIR__ . "/../vendor/autoload.php";

ini_set('display_errors', true);

const DATABASE_HOST = '127.0.0.1';
const DATABASE_PORT = 5432;
const DATABASE_NAME = 'search';
const DATABASE_CHARSET = 'utf8';
const DATABASE_USERNAME = 'postgres';
const DATABASE_PASSWORD = 'password';
const LINKS_TABLE = 'links_statics';
const POSTGRES_POOL_SIZE = 64;

$cli = new CliPrinter();

\Swoole\Coroutine::create(function () {
    $connectionConfig = [
        'host' => DATABASE_HOST,
        'port' => DATABASE_PORT,
        'dbname' => DATABASE_NAME,
        'charset' => DATABASE_CHARSET,
        'user' => DATABASE_USERNAME,
        'password' => DATABASE_PASSWORD,
    ];
    $requestData = [
        'os' => '192.168.1.1',
        'os_version' => '192.168.1.1',
        'browser' => '192.168.1.1',
        'browser_version' => '192.168.1.1',
        'client_ip' => '192.168.1.1',
        'base_url' => '192.168.1.1',
        'url_path' => '192.168.1.1',
        'full_url' => '192.168.1.1',
        'created_at' => (new DateTime('now'))->format('Y-m-d H:i:s') ,
    ];

    $manager = new PostgresConnectionManager($connectionConfig);
    $manager->initializeConnections();

    for ($queryId = 1 ; $queryId <= 2 ; $queryId ++){

        \Swoole\Coroutine::create(function () use ($manager , $requestData){
            $pg = $manager->channel->pop();
            $manager->saveLinkStatics($pg ,$requestData , LINKS_TABLE);
            $manager->channel->push($pg);
            \Swoole\Coroutine::sleep(1);
        });

    }
});

