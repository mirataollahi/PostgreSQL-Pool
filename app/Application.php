<?php

namespace App;


use DateTime;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Server;
use Swoole\Runtime;
use Swoole\Server as SwooleServer;

class Application
{

    private SwooleServer $socketServer;
    private CliLogger $cli;
    private PostgresConnectionManager $postgresPool;

    private bool $serverStarted = false;

    public function __construct()
    {
        $this->cli = new CliLogger();
        $this->init();
    }

    /**
     * Initial the socket server and postgres connection pool
     *
     * @return void
     */
    private function init(): void
    {
        $connectionConfig = [
            'host' => DATABASE_HOST,
            'port' => DATABASE_PORT,
            'dbname' => DATABASE_NAME,
            'charset' => DATABASE_CHARSET,
            'user' => DATABASE_USERNAME,
            'password' => DATABASE_PASSWORD,
        ];

        $this->socketServer = new SwooleServer(SOCKET_SERVER_HOST, SOCKET_SERVER_PORT);
        $this->socketServer->set([
            'worker_num' => 2,
        ]);
        $this->postgresPool = new PostgresConnectionManager($connectionConfig);


        /*
         * On socket server received request event
         */
        $this->socketServer->on('receive', function (SwooleServer $server, $fd, $reactor_id, $data)  {
            $this->cli->display('info', "New message form socket client #{$fd}");
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

                // $pg = $this->postgresPool->channel->pop();
                $this->postgresPool->saveLinkStatics($requestData , LINKS_TABLE);
                // $this->postgresPool->channel->push($pg);
            });

            $response = ['status' => true];
            $server->send($fd, json_encode($response));
        });

        /*
         * On socket server started event
         */
        $this->socketServer->on('start', function () {
            $this->cli->display('info', "TCP Socket Server started at " . SOCKET_SERVER_HOST . ':' . SOCKET_SERVER_PORT);
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
     * Start the socket server and binding in network
     *
     * @return void
     */
    public function run(): void
    {
        if (!$this->serverStarted) {
//            $coId  = Coroutine::create(function (){
//                $this->postgresPool->initializeConnections();
//            });
            Runtime::enableCoroutine();
            $this->socketServer->start();
            $this->serverStarted = true;
        } else {
            $this->cli->display("warning", "Server is already running and cannot be started again.");
        }
    }
}

