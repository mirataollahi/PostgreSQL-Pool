<?php

namespace App\Database;

use App\Core\Config;
use App\Logger\CliLogger;
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
    private int $connectionCount = 0 ;


    private int|float $timeout = -1;

    /**
     * max connection pool size
     *
     * @var int
     */
    private int $maxSize = 16;


    public function __construct()
    {
        $this->cliPrinter = new CliLogger();
        $this->channel = new Channel($this->maxSize);
        $this->maxSize = (int) Config::get('POSTGRES_POOL_SIZE' , 16);
    }

    /**
     * Create Connections and push in connections channel (pool)
     *
     * @return void
     */
    public function initializeConnections(): void
    {
        while ($this->maxSize > $this->connectionCount) {
            $this->make();
        }
    }

    /**
     * Get a database connection before each transaction
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        if ($this->channel->isEmpty() && $this->connectionCount < $this->maxSize) {
            $this->make();
        }
        return $this->channel->pop($this->timeout);
    }

    /**
     * Release database connection and put in connections pool
     *
     * @param PDO|null $connection
     * @return void
     */
    public function releaseConnection(PDO|null $connection = null): void
    {
        if ($connection !== null) {
            $this->channel->push($connection);
        }
        else {
            /* connection broken */
            $this->connectionCount -= 1;
            $this->make();
        }
    }


    /**
     * Make a pdo connection and push to connections pool
     *
     * @return void
     */
    public function make(): void
    {
        $connectionConfig = [
            'host' => Config::get('DATABASE_HOST'),
            'port' => Config::get('DATABASE_PORT'),
            'dbname' => Config::get('DATABASE_NAME'),
            'charset' => Config::get('DATABASE_CHARSET'),
            'user' => Config::get('DATABASE_USERNAME'),
            'password' => Config::get('DATABASE_PASSWORD'),
        ];
        $pdo = new PDO(
            "pgsql:host={$connectionConfig['host']};port={$connectionConfig['port']};dbname={$connectionConfig['dbname']};user={$connectionConfig['user']};password={$connectionConfig['password']}",
            $connectionConfig['user'],
            $connectionConfig['password']
        );
        $this->channel->push($pdo);
        $this->connectionCount++;
    }

    /**
     * Store like statics in database using postgres channel
     *
     * @param array $requestData
     * @return void
     */
    public function saveLinkStatics(array $requestData = []): void
    {
        if ($this->connectionCount === 0)
            $this->initializeConnections();
        $pdo = $this->getConnection();

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
        catch (\Exception $exception) {
            $this->cliPrinter->display('critical' , $exception->getMessage());
        }
        finally {
            $this->releaseConnection($pdo);
        }
    }
}