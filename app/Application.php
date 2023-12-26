<?php
namespace App;

use DateTime;
use Swoole\Coroutine;
use Swoole\Server;
use Swoole\Runtime;
use Swoole\Server as SwooleServer;

class Application
{

    /**
     * The swoole socket server driver
     *
     * @var SwooleServer
     */
    private SwooleServer $socketServer;

    /**
     * Command line logger factory
     *
     * @var CliLogger
     */
    private CliLogger $cli;

    /**
     * The postgres connection manager
     *
     * @var PostgresConnectionManager
     */
    private PostgresConnectionManager $postgresPool;

    /**
     * The current socket server starting or not
     *
     * @var bool
     */
    private bool $serverStarted = false;

    /**
     * Create a new swoole socket server to store link statics
     *
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initial the socket server and postgres connection pool
     *
     * @return void
     */
    private function init(): void
    {
        $this->cli = new CliLogger();
        $this->makeSocketServer();
        $this->postgresPool = new PostgresConnectionManager();

        /*
         * Define socket server events
         */
        $this->socketServer->on('receive', [$this, 'onReceive']);

        $this->socketServer->on('start', [$this, 'onStart']);

        $this->socketServer->on('connect', [$this, 'onConnect']);

        $this->socketServer->on('close', [$this, 'onClose']);

    }


    /**
     * Create a socket server with environment host , port and workers number
     *
     * @return void
     */
    public function makeSocketServer(): void
    {
        $socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
        $socketPort = Config::get('SOCKET_SERVER_PORT' , 8100);
        $workerNumber = Config::get('SOCKET_WORKER_NUMBER' , 1);
        $this->socketServer = new SwooleServer($socketHost, (int)$socketPort);
        $this->socketServer->set(['worker_num' => $workerNumber]);
    }

    /**
     * Start the socket server and binding in network
     *
     * @return void
     */
    public function run(): void
    {
        if (!$this->serverStarted) {
            Runtime::enableCoroutine();
            $this->socketServer->start();
            $this->serverStarted = true;
        } else {
            $this->cli->display("warning", "Server is already running and cannot be started again.");
        }
    }

    /**
     * Handle the received request data from a socket client.
     * Convert the URL string in the client data into an associative array
     *
     * @param SwooleServer $server
     * @param int $fd
     * @param int $reactorId
     * @param string $data
     * @return void
     */
    public function onReceive(SwooleServer $server, int $fd, int $reactorId, string $data): void
    {
        $this->cli->display('info', "New message form socket client #{$fd}");
        $clientData = json_decode($data , true);

        $userAgent = UserAgent::create($clientData['ua'] ?? null);
        $urlParser = UrlHelper::toArray($clientData['url'] ?? null);


        Coroutine::create(function () use ($clientData , $userAgent , $urlParser){
            $requestData = [
                'os' => $userAgent->osFamily,
                'os_version' => $userAgent->osMajor,
                'browser' => $userAgent->agentFamily,
                'browser_version' => $userAgent->agentVersion,
                'client_ip' => $clientData['client_ip'] ?? null,
                'base_url' => $urlParser['base_url'],
                'url_path' => $urlParser['url_path'],
                'full_url' => $urlParser['pure_url'],
                'created_at' => (new DateTime('now'))->format('Y-m-d H:i:s') ,
            ];
            $this->postgresPool->saveLinkStatics($requestData);
        });
        $server->send($fd, json_encode(['status' => true]));
    }

    /**
     * Handle the event when the TCP socket server starts.
     *
     * @return void
     */
    public function onStart(): void
    {
        // Display information when the TCP socket server starts.
        $this->cli->display('info', "TCP Socket Server started at " . $this->socketServer->host . ':' . $this->socketServer->port);
    }

    /**
     * Handle the event when a new client connects to the TCP socket server.
     *
     * @param SwooleServer $server The server instance.
     * @param int    $fd     The file descriptor (ID) of the new client connection.
     *
     * @return void
     */
    public function onConnect(SwooleServer $server, int $fd): void
    {
        // Display information about a new client connection.
        $this->cli->display("info", "New client connection with id: #{$fd}");
    }

    /**
     * Handle the event when a client connection is closed on the TCP socket server.
     *
     * @param SwooleServer $server The server instance.
     * @param int    $fd     The file descriptor (ID) of the closed client connection.
     *
     * @return void
     */
    public function onClose(SwooleServer $server, int $fd): void
    {
        // Display information when a client connection is closed.
        $this->cli->display('info', "Client connection closed with id #{$fd}");
    }

}

