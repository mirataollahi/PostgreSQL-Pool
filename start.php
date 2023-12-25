<?php



require __DIR__ . '/vendor/autoload.php';


/*
 * Socket Server Information
 */
const SOCKET_SERVER_HOST = '127.0.0.1';
const SOCKET_SERVER_PORT = 8100;


/*
 * Database Information
 */
const DATABASE_HOST = '127.0.0.1';
const DATABASE_PORT = 5432;
const DATABASE_NAME = 'api_test';
const DATABASE_CHARSET = 'utf8';
const DATABASE_USERNAME = 'postgres';
const DATABASE_PASSWORD = 'password';
const POSTGRES_POOL_SIZE = 64;
const LINKS_TABLE = 'links_statics';


/*
 * Create new instance of the swoole postgres proxy
 *
 */

$app = new App\Application();
$app->run();
