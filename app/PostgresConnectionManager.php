<?php

namespace App;

use PDO;
use Swoole\Coroutine\Channel;

class PostgresConnectionManager
{
    public Channel $channel;
    private array $connectionConfig;
    private CliLogger $cliPrinter;

    private static int $connectionCount = 0 ;


    public function __construct(array $connectionConfig)
    {
        $this->cliPrinter = new CliLogger();
        $this->channel = new Channel(64);
        $this->connectionConfig = $connectionConfig;
    }

    public function initializeConnections(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $pdo = $this->make();
            $this->channel->push($pdo);
            static::$connectionCount++;
        }
    }

    public function make(): PDO
    {
        return new PDO(
            "pgsql:host={$this->connectionConfig['host']};port={$this->connectionConfig['port']};dbname={$this->connectionConfig['dbname']};user={$this->connectionConfig['user']};password={$this->connectionConfig['password']}",
            $this->connectionConfig['user'],
            $this->connectionConfig['password']
        );
    }

    public function saveLinkStatics( $requestData = [] , string $tableName = 'links_statics'): void
    {
        if (static::$connectionCount === 0)
            $this->initializeConnections();


        $pdo = $this->channel->pop();
        try {
            $stmt = $pdo->prepare("INSERT INTO " . $tableName . " (os, os_version, browser, browser_version, client_ip, base_url, url_path, full_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $requestData['os']);
            $stmt->bindParam(2, $requestData['os_version']);
            $stmt->bindParam(3, $requestData['browser']);
            $stmt->bindParam(4, $requestData['browser_version']);
            $stmt->bindParam(5, $requestData['client_ip']);
            $stmt->bindParam(6, $requestData['base_url']);
            $stmt->bindParam(7, $requestData['url_path']);
            $stmt->bindParam(8, $requestData['full_url']);
            $stmt->bindParam(9, $requestData['created_at']);
            $stmt->execute();
            $this->cliPrinter->display('debug' , "Like statics saved");
        }
        catch (\Exception $exception)
        {
            $this->cliPrinter->display('critical' , $exception->getMessage());
        } finally {
            $this->channel->push($pdo);
        }
    }
}