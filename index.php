<?php


use App\Config;

require __DIR__ . "/vendor/autoload.php";

ini_set('display_errors' , true);



$status = Config::get('IS_PRODUCTION') === 'false';
var_dump($status);