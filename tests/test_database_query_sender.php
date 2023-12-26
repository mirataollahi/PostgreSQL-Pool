<?php


require __DIR__ . "/../vendor/autoload.php";




use Swoole\Coroutine;
use Swoole\Coroutine\PostgreSQL;
use Swoole\Database\PDOConfig;
use Josantonius\CliPrinter\CliPrinter;
use Swoole\Database\PDOPool;

const DATABASE_HOST = '127.0.0.1';
const DATABASE_PORT = 5432;
const DATABASE_NAME = 'search';
const DATABASE_CHARSET = 'utf8';
const DATABASE_USERNAME = 'postgres';
const DATABASE_PASSWORD = 'password';
const DATABASE_SCHEMA = null;
const POSTGRES_POOL_SIZE = 64;
const LINKS_TABLE = 'links_statics';

const IS_PRODUCTION = false;



$cli = new CliPrinter();



$s = microtime(true);


function createPdoConfig(): PDOConfig
{
    return (new PDOConfig)
        ->withHost('127.0.0.1')
        ->withPort(5432)
        ->withDbName('api_test')
        ->withCharset('utf8mb4')
        ->withUsername('root')
        ->withPassword('password');
}


$pdoConfig = createPdoConfig();

$pool = new PDOPool($pdoConfig , 64);


for ($queryNumber = 1 ; $queryNumber <= 10 ; $queryNumber ++)
{
    Coroutine::create(function () use ($cli){


    });
}

