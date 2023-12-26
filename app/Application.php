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
         * On socket server received request event
         */
        $this->socketServer->on('receive', function (SwooleServer $server, $fd, $reactor_id, $data)  {
            $this->handleReceivedRequest($data , $fd);
            $server->send($fd, json_encode(['status' => true]));
        });
        /*
         * On socket server started event
         */
        $this->socketServer->on('start', function () {
            $this->cli->display('info', "TCP Socket Server started at " . $this->socketServer->host . ':' . $this->socketServer->port);
        });
        /*
         * On socket server client connected event
         */
        $this->socketServer->on('connect', function ($server, $fd) {
            $this->cli->display("info", "New client connection with id: #{$fd}");
        });
        /*
         * On socket client connection closed event
         */
        $this->socketServer->on('close', function (Server $server , $fd) {
            $this->cli->display('info', "Client connection closed with id #{$fd}");
        });
    }

    /**
     * Handler received new message contain link statics event
     *
     * @param mixed $data
     * @param int|string|null $clientId
     * @return void
     */
    public function handleReceivedRequest(mixed $data , int|string|null $clientId = null): void
    {
        $this->cli->display('info', "New message form socket client #{$clientId}");
        $clientData = json_decode($data , true);
        $userAgent = UserAgent::create($clientData['ua'] ?? null);

        $urlParser = UrlHelper::toArray($clientData['url'] ?? null);
        $trimmed_url = trim(($urlParser['host'] ?? null) ?: ($urlParser['path'] ?? null), " \t\n\r\0\x0B/‌‍");
        $finalUrl = strtolower($trimmed_url);
        $urlPath = strtolower(trim(
            empty(($urlParser['host'] ?? null)) ? '' : ($urlParser['path'] ?? null), " \t\n\r\0\x0B/‌‍"
        ));

        Coroutine::create(function () use ($clientData , $userAgent , $finalUrl , $urlPath){
            $requestData = [
                'os' => $userAgent->osFamily,
                'os_version' => $userAgent->osMajor,
                'browser' => $userAgent->agentFamily,
                'browser_version' => $userAgent->agentVersion,
                'client_ip' => $clientData['client_ip'] ?? null,
                'base_url' => $finalUrl,
                'url_path' => $urlPath,
                'full_url' => $clientData['url'] ?? null,
                'created_at' => (new DateTime('now'))->format('Y-m-d H:i:s') ,
            ];
            $this->postgresPool->saveLinkStatics($requestData);
        });
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
}

