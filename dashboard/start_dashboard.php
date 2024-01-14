<?php

require __DIR__ . '/../vendor/autoload.php';
const BASE_PATH = __DIR__ . '/../';
use App\Core\Config;
use Swoole\Http\Server as HttpServer;
use Swoole\Coroutine\Client as ClientSocket;
use Swoole\Http\Request;
use Swoole\Http\Response;


class Dashboard
{
    private string $httpHost;
    private int $httpPort;
    private string $socketHost;
    private int $socketPort;
    private HttpServer $httpServer;
    private ClientSocket $clientSocket;

    public function __construct()
    {
        $this->httpHost = Config::get('DASHBOARD_HOST', '0.0.0.0');
        $this->httpPort = Config::get('DASHBOARD_PORT', 8105);
        $this->socketHost = Config::get('SOCKET_SERVER_HOST', '127.0.0.1');
        $this->socketPort = Config::get('SOCKET_SERVER_PORT', 8100);
        $this->httpServer = new HttpServer($this->httpHost, $this->httpPort);
    }

    /**
     * Register http server events handler and start the server
     *
     * @return void
     */
    public function start(): void
    {
        $this->httpServer->on('start', [$this, 'onHttpServerStart']);
        $this->httpServer->on('request', [$this , 'onHttpServerRequest']);
        $this->httpServer->start();
    }

    /**
     * Http server starting event handler
     *
     * @return void
     */
    public function onHttpServerStart(): void
    {
        $this->clientSocket = new ClientSocket(SWOOLE_SOCK_TCP);
        echo PHP_EOL . "\033[32mHttp server started on http://{$this->httpHost}:{$this->httpPort} \033[0m" . PHP_EOL;
        $this->clientSocket->connect($this->socketHost, $this->socketPort, 0.5);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function onHttpServerRequest(Request $request, Response $response): void
    {
        $path = $request->server['request_uri'];

        /*
         * Dashboard Router
         */
        match ($path) {
            '/status' => $this->handleGetStatsRequest($request, $response),
            '/' => $this->handleIndexPageRequest($request, $response),
            default => $this->notFoundPageRequest($response),
        };
    }

    /**
     * Handle on get status request
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function handleGetStatsRequest(Request $request, Response $response): void
    {
        $this->clientSocket->connect($this->socketHost, $this->socketPort, 0.5);
        if ($this->clientSocket->isConnected()) {
            $responseFromSocket = $this->getStatsFromSocket();
            $this->jsonResponse($response , $responseFromSocket);
        } else {
            $this->serverErrorResponse($response , "Error connecting to the socket server");
        }
    }

    /**
     * Dashboard index page route handler
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function handleIndexPageRequest(Request $request, Response $response): void
    {
        $this->htmlResponse($response , 'index.html');
    }


    /**
     * Not found page route handler
     *
     * @param Response $response
     * @return void
     */
    public function notFoundPageRequest(Response $response): void
    {
        $response->status(404);
        $response->end("Not Found");
    }


    public function getStatsFromSocket():string|array|null
    {
        try {
            $socketData = ['monitor_client' => true];
            if ($this->clientSocket->isConnected()) {
                $this->clientSocket->send(json_encode($socketData));
                return $this->clientSocket->recv();
            }
            return null;
        }
        catch (Exception $exception){
            return null;
        }
    }

    /**
     * return json response
     *
     * @param Response $response
     * @param mixed $data
     * @return void
     */
    public function jsonResponse(Response $response , mixed $data): void
    {
        $response->header('Content-Type', 'application/json');
        $response->end($data);
    }

    /**
     * return server error with 500 status code
     *
     * @param Response $response
     * @param string|null $message
     * @return void
     */
    public function serverErrorResponse(Response $response , string|null $message = null): void
    {
        $response->status(500);
        $response->end(json_encode([
            'message' => $message
        ]));
    }

    /**
     * return text/html response with html file address
     *
     * @param Response $response
     * @param string $viewAddress
     * @return void
     */
    public function htmlResponse(Response $response , string $viewAddress): void
    {
        $indexContent = file_get_contents(__DIR__ .'/'. $viewAddress);
        $response->header('Content-Type', 'text/html');
        $response->end($indexContent);
    }


}


$httpServer = new Dashboard();
$httpServer->start();
