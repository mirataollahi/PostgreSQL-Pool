<?php



require __DIR__ . '/vendor/autoload.php';


/*
 * Socket Server Information
 */
const SOCKET_SERVER_HOST = '127.0.0.1';
const SOCKET_SERVER_PORT = 8101;


/*
 * Database Information
 */

const DATABASE_HOST = '127.0.0.1';
const DATABASE_PORT = 5432;
const DATABASE_NAME = 'search';
const DATABASE_CHARSET = 'utf8';
const DATABASE_USERNAME = 'postgres';
const DATABASE_PASSWORD = 'password';
const DATABASE_SCHEMA = 'public';
const POSTGRES_POOL_SIZE = 16;
const LINKS_TABLE = 'links_statics';

const IS_PRODUCTION = false;

/*
 * Create new instance of the swoole postgres proxy
 *
 */

Swoole\Runtime::enableCoroutine();
$app = new App\Application();
$app->run();
