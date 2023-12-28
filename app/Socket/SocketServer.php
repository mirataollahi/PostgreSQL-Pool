<?php

namespace App\Socket;

use App\Core\Config;
use App\Core\UrlHelper;
use App\Core\UserAgent;
use App\Database\PostgresConnectionManager;
use App\Logger\CliLogger;
use DateTime;
use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Runtime;
use Swoole\Server as SwooleServer;

class SocketServer
{
    /**
     * Received message counter in current socket server
     *
     * @var Atomic
     */
    public Atomic $receivedMessages ;

    /**
     * Current online client in the socket server
     *
     * @var Atomic
     */
    public Atomic $currentClients ;

    /**
     * Socket server all connected client connection
     *
     * @var Atomic
     */
    public Atomic $allConnectedClients ;

    /**
     * Socket server all closed client connection
     *
     * @var Atomic
     */
    public Atomic $allClosedClient ;

    /**
     * Socket server running status
     *
     * @var bool
     */

    private bool $serverStarted = false;

    /**
     * The postgres connection manager
     *
     * @var PostgresConnectionManager
     */
    public PostgresConnectionManager $postgresPool;

    /**
     * Command line logger factory
     *
     * @var CliLogger
     */
    private CliLogger $cli;

    /**
     * The swoole socket server driver
     *
     * @var SwooleServer
     */
    private SwooleServer $socketDriver;


    /**
     * Create a new instance of socket server with existing logger service or new one
     *
     * @param CliLogger|null $logger
     */
    public function __construct(?CliLogger $logger = null)
    {
        $this->cli = is_null($logger) ? new CliLogger() : $logger;
    }

    public function init(): static
    {
        $this->makeSocketServer();
        $this->postgresPool = new PostgresConnectionManager();
        $this->receivedMessages = new Atomic(0);
        $this->currentClients = new Atomic(0);
        $this->allConnectedClients = new Atomic(0);
        $this->allClosedClient = new Atomic(0);

        return $this->setEvents();
    }

    /**
     * Set socket server callback events
     *
     * @return $this
     */
    public function setEvents(): static
    {
        $this->socketDriver->on('receive', [$this, 'onReceive']);
        $this->socketDriver->on('start', [$this, 'onStart']);
        $this->socketDriver->on('connect', [$this, 'onConnect']);
        $this->socketDriver->on('close', [$this, 'onClose']);

        return $this;
    }

    /**
     * Create a socket server with environment host , port and workers number
     *
     * @return static
     */
    public function makeSocketServer(): static
    {
        $socketHost = Config::get('SOCKET_SERVER_HOST' , '127.0.0.1');
        $socketPort = Config::get('SOCKET_SERVER_PORT' , 8100);
        $workerNumber = Config::get('SOCKET_WORKER_NUMBER' , 1);
        $this->socketDriver = new SwooleServer($socketHost, (int)$socketPort);
        $this->socketDriver->set(['worker_num' => $workerNumber]);
        return $this;
    }

    /**
     * Handle the event when the TCP socket server starts.
     *
     * @return void
     */
    public function onStart(): void
    {
        // Display information when the TCP socket server starts.
        $this->cli->display('info', "TCP Socket Server started at " . $this->socketDriver->host . ':' . $this->socketDriver->port);

        Coroutine::create(function (){
            $this->cli->showApplicationStatus($this);
        });
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
        $this->currentClients->add(1);
        $this->allConnectedClients->add(1);
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
        $this->allClosedClient->add(1);
        $this->currentClients->sub(1);
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

        if (array_key_exists('monitor_client' , $clientData ?? []))
        {
            $response = [
                'status' => true ,
                'received_messages' => number_format($this?->receivedMessages->get() ?: 0) ,
                'current_clients' => number_format($this->currentClients?->get() ?: 0) ,
                'all_connected_clients' => number_format($this->allConnectedClients?->get() ?: 0) ,
                'all_closed_client' => number_format($this->allClosedClient?->get() ?: 0) ,
                'database_connection_count' => $this->postgresPool->getConnectionCount() ,
            ];
        }
        else {
            $this->receivedMessages->add();
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
            $response = ['status' => true];
        }

        $server->send($fd, json_encode($response));
    }

    /**
     * Start the socket server and binding in network
     *
     * @return void
     */
    public function start(): void
    {
        if (!$this->serverStarted) {
            Runtime::enableCoroutine();
            $this->socketDriver->start();
            $this->serverStarted = true;
        } else {
            $this->cli->display("warning", "Server is already running and cannot be started again.");
        }
    }
}