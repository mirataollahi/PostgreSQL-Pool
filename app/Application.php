<?php

namespace App;


use DateTime;
use Swoole\Coroutine;
use Swoole\Server;
use Swoole\Runtime;
use Swoole\Server as SwooleServer;
use Josantonius\CliPrinter\CliPrinter;

class Application
{

    private SwooleServer $socketServer;
    private CliPrinter $cli;
    private PostgresConnectionManager $postgresPool;

    public function __construct()
    {
        Runtime::enableCoroutine();
        $this->cli = new CliPrinter();
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

//        $this->postgresPool = new PostgresConnectionManager($connectionConfig);
        $this->socketServer = new SwooleServer(SOCKET_SERVER_HOST, SOCKET_SERVER_PORT);


        /*
         * On socket server received request event
         */
        $this->socketServer->on('receive', function (SwooleServer $server, $fd, $reactor_id, $data)  {
            $this->cli->display('info', "New message form socket client #{$fd} with data =" . json_encode($data ?? []));

//            $userAgent = UserAgent::create($data['ua']);
//            $url = UrlHelper::toArray($data['url']);
//
//            Coroutine::create(function () use ($data , $userAgent , $url){
//                $requestData = [
//                    'os' => $userAgent->osFamily,
//                    'os_version' => $userAgent->osMajor,
//                    'browser' => $userAgent->agentFamily,
//                    'browser_version' => $userAgent->agentVersion,
//                    'client_ip' => $data['client_ip'],
//                    'base_url' => $url['scheme']  +  $url['host'],
//                    'url_path' => $url['path'],
//                    'full_url' => UrlHelper::trimUrl($data['url']),
//                    'created_at' => (new DateTime('now'))->format('Y-m-d H:i:s') ,
//                ];
//
//
//                $pg = $this->postgresPool->channel->pop();
//                $this->postgresPool->saveLinkStatics($pg ,$requestData , LINKS_TABLE);
//                $this->postgresPool->channel->push($pg);
//            });

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
        $this->socketServer->start();
    }
}

