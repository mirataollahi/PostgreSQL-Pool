<?php

namespace App;

use PDO;
use Swoole\Coroutine\Channel;

class PostgresConnectionManager
{
    /**
     * The database connections pool
     *
     * @var Channel
     */
    public Channel $channel;

    /**
     * Command line logger service
     *
     * @var CliLogger
     */
    private CliLogger $cliPrinter;

    /**
     * Current database connection numbers
     *
     * @var int
     */
    private static int $connectionCount = 0 ;


    public function __construct()
    {
        $this->cliPrinter = new CliLogger();
        $this->channel = new Channel(64);
    }

    /**
     * Create Connections and push in connections channel (pool)
     *
     * @return void
     */
    public function initializeConnections(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $pdo = $this->make();
            $this->channel->push($pdo);
            static::$connectionCount++;
        }
    }

    /**
     * Make a pdo connection
     *
     * @return PDO
     */
    public function make(): PDO
    {
        $connectionConfig = [
            'host' => Config::get('DATABASE_HOST'),
            'port' => Config::get('DATABASE_PORT'),
            'dbname' => Config::get('DATABASE_NAME'),
            'charset' => Config::get('DATABASE_CHARSET'),
            'user' => Config::get('DATABASE_USERNAME'),
            'password' => Config::get('DATABASE_PASSWORD'),
        ];

        return new PDO(
            "pgsql:host={$connectionConfig['host']};port={$connectionConfig['port']};dbname={$connectionConfig['dbname']};user={$connectionConfig['user']};password={$connectionConfig['password']}",
            $connectionConfig['user'],
            $connectionConfig['password']
        );
    }

    /**
     * Store like statics in database using postgres channel
     *
     * @param array $requestData
     * @return void
     */
    public function saveLinkStatics(array $requestData = []): void
    {
        if (static::$connectionCount === 0)
            $this->initializeConnections();
        $pdo = $this->channel->pop();
        try {
            $databaseSchema = Config::get('DATABASE_SCHEMA');
            $linksTable = Config::get('LINKS_TABLE');
            $stmt = $pdo->prepare("INSERT INTO $databaseSchema.$linksTable (os, os_version, browser, browser_version, client_ip, base_url, url_path, full_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $requestData['os']);
            $stmt->bindParam(2, $requestData['os_version'] );
            $stmt->bindParam(3, $requestData['browser']);
            $stmt->bindParam(4, $requestData['browser_version'] );
            $stmt->bindParam(5, $requestData['client_ip'] );
            $stmt->bindParam(6, $requestData['base_url'] );
            $stmt->bindParam(7, $requestData['url_path'] );
            $stmt->bindParam(8, $requestData['full_url'] );
            $stmt->bindParam(9, $requestData['created_at'] );
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