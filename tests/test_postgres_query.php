<?php


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


    // Create a PDO connection
    $pdo = new PDO(
        "pgsql:host={$connectionConfig['host']};port={$connectionConfig['port']};dbname={$connectionConfig['dbname']};user={$connectionConfig['user']};password={$connectionConfig['password']}",
        $connectionConfig['user'],
        $connectionConfig['password']
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    for ($queryId = 1 ; $queryId <= 2 ; $queryId ++){

        \Swoole\Coroutine::create(function () use ($requestData , $pdo){
            try {
                // Prepare an SQL statement for insertion
                $stmt = $pdo->prepare("INSERT INTO " . LINKS_TABLE . " (os, os_version, browser, browser_version, client_ip, base_url, url_path, full_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bindParam(1, $requestData['os']);
                $stmt->bindParam(2, $requestData['os_version']);
                $stmt->bindParam(3, $requestData['browser']);
                $stmt->bindParam(4, $requestData['browser_version']);
                $stmt->bindParam(5, $requestData['client_ip']);
                $stmt->bindParam(6, $requestData['base_url']);
                $stmt->bindParam(7, $requestData['url_path']);
                $stmt->bindParam(8, $requestData['full_url']);
                $stmt->bindParam(9, $requestData['created_at']);
                $stmt->execute();
            } catch (PDOException $e) {
                // Handle exceptions (e.g., log or print an error message)
                echo "Error: " . $e->getMessage() . PHP_EOL;
            }
        });

    }
});

