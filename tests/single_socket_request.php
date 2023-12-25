<?php



storeLinkStatics();


function storeLinkStatics(): void
{
    $socketHost = '127.0.0.1';
    $socketPort = 8100;
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
        'ua' => 'USER_AGENT' ,
        'client_ip' => 'CLIENT_IP' ,
        'url' => 'PURE_URL' ,
    ];
    $requestData = json_encode($linkStatics);
    fwrite($socket, $requestData);
    $response = fread($socket, 4096);
    fclose($socket);
}