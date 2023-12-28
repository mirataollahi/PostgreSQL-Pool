<?php


require __DIR__ . '/vendor/autoload.php';
use App\Core\Config;
const BASE_PATH = __DIR__ ;


$httpHost = Config::get('DASHBOARD_HOST' , '0.0.0.0');
$httpPort = Config::get('DASHBOARD_PORT' , 8105);
$http = new Swoole\Http\Server($httpHost, $httpPort);


$socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
$socketPort = Config::get('SOCKET_SERVER_PORT' , 8100);


$http->on('request', function ($request, $response)  {
    // Check the request path
    $path = $request->server['request_uri'];

    if ($path === '/status') {
        // Data to be sent to the TCP socket server
        $socketData = [
            'monitor_client' => true,
        ];

        $clientSocket = new Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        $clientSocket->connect('localhost', 8100, 0.5);



        if ($clientSocket->isConnected()) {
            // Send data to the TCP socket server
            $clientSocket->send(json_encode($socketData));

            // Receive the response from the TCP socket server
            $responseFromSocket = $clientSocket->recv();

            // Send the response back to the HTTP client
            $response->header('Content-Type', 'application/json');
            $response->end($responseFromSocket);
        } else {
            $response->status(500);
            $response->end("Error connecting to the socket server");
        }
    } elseif ($path === '/') {
        // Serve the index.html file
        $indexContent = file_get_contents(__DIR__ . '/index.html');
        $response->header('Content-Type', 'text/html');
        $response->end($indexContent);
    } else {
        // Handle other paths (404 Not Found)
        $response->status(404);
        $response->end("Not Found");
    }
});

echo PHP_EOL . "\033[32mHttp server started on http://$httpHost:$httpPort \033[0m" . PHP_EOL;
$http->start();
