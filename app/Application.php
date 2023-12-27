<?php

namespace App;


class Application
{

    /**
     * Create a new swoole socket server to store link statics
     *
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initial socket server and start
     *
     * @return void
     */
    private function init(): void
    {
        $socketServer = new SocketServer();
        $socketServer->init()->start();
    }
}

