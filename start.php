<?php

require __DIR__ . '/vendor/autoload.php';




/*
 * Create new instance of the swoole postgres proxy
 *
 */

Swoole\Runtime::enableCoroutine();
$app = new App\Application();
$app->run();
