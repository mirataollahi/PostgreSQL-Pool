<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config;

storeLinkStatics();

function storeLinkStatics(): void
{
    $socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
    $socketPort = Config::get('SOCKET_SERVER_PORT' , 8100);
    $context = stream_context_create([
        'socket' => [
            'timeout' => 1 ,
        ],
    ]);
    $socket = stream_socket_client("tcp://$socketHost:$socketPort", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        die("Error: $errstr ($errno)\n");
    }
    $linkStatics = [
        'ua' => 'Mozilla/5.0 (Linux; Android 13; SM-S901B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36' ,
        'client_ip' => '2.185.145.157' ,
        'url' => 'https://basalam.com/cart' ,
    ];
    $requestData = json_encode($linkStatics);
    fwrite($socket, $requestData);
    $response = fread($socket, 4096);
    fclose($socket);
}