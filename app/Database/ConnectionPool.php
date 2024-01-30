<?php

namespace App\Database;

use App\Core\Config;
use App\Database\Connector\ConnectorInterface;
use App\Logger\CliLogger;
use PDO;
use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use WeakMap;


class ConnectionPool implements ConnectionPoolInterface
{
    /**
     * Whether the connection pool is initialized
     *
     * @var bool
     */
    protected bool $initialized = false;


    /**
     * The database connections pool
     *
     * @var Channel
     */
    public Channel $pool;

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

    /**
     * Database connection connector driver
     *
     * @var ConnectorInterface
     */
    protected ConnectorInterface $connector;

    /**
     * The last active time of connection
     *
     * @var WeakMap
     */
    protected WeakMap $lastActiveTime;


    public Atomic $allQuery;
    public Atomic $successQuery;
    public Atomic $failQuery;
    public bool $closed = false;

    public function __construct(ConnectorInterface $connector)
    {
        $this->cliPrinter = new CliLogger();
        $this->pool = new Channel($this->maxSize);
        $this->maxSize = (int) Config::get('POSTGRES_POOL_SIZE' , 16);
        $this->allQuery = new Atomic(0);
        $this->successQuery = new Atomic(0);
        $this->failQuery = new Atomic(0);
        $this->lastActiveTime = new WeakMap();
        $this->connector = $connector;
    }

    /**
     * Create Connections and push in connections channel (pool)
     *
     * @return bool
     */
    public function init(): bool
    {
        if (!$this->initialized) {
            while ($this->maxSize > $this->connectionCount) {
                $this->make();
            }
            $this->initialized = true;
        }
        return true;
    }

    /**
     * Get a database connection before each transaction
     *
     * @return PDO|bool|null
     */
    public function getConnection(): PDO|null|bool
    {
        if ($this->connectionCount === 0)
        {
            $this->init();
        }
        if ($this->pool->isEmpty() && $this->connectionCount < $this->maxSize) {
            $this->make();
        }
        return $this->pool->pop(3);
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
            $this->pool->push($connection);
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
        try {
            $connection = $this->createConnection();
            $this->pool->push($connection , 3);
            $this->connectionCount++;
        } catch (\Exception $exception)
        {
            sleep(5);
            $this->cliPrinter->display('critical' , $exception->getMessage());
        }
    }

    protected function createConnection(): PDO
    {
        $this->connectionCount++;
        $connection = $this->connector->connect();
        $this->lastActiveTime[$connection] = time();
        return $connection;
    }


    protected function removeConnection(mixed $connection): void
    {
        $this->connectionCount--;
        Coroutine::create(function () use ($connection) {
            try {
                $this->connector->disconnect($connection);
            } catch (\Throwable $e) {
                // Ignore this exception.
            }
        });
    }


    /**
     * Store like statics in database using postgres channel
     *
     * @param array $requestData
     * @return void
     */
    public function saveLinkStatics(array $requestData = []): void
    {
        $this->allQuery->add();
        $pdo = $this->getConnection();
        if ($pdo)
        {
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
                $isSaved = $stmt->execute();
                if ($isSaved) {
                    $this->cliPrinter->display('debug', "Like statics saved");
                    $this->successQuery->add();
                    return;
                }
            }
            catch (\Exception $exception) {
                $this->cliPrinter->display('critical' , $exception->getMessage());
                $isSaved = false;
                $this->failQuery->add();
                return;
            }
            finally {
                if ( (!$this->isConnectionValid($pdo))  && (!$isSaved) )
                {
                    unset($pdo);
                    $this->connectionCount--;
                } else
                    $this->releaseConnection($pdo);
            }
        }
        else $this->failQuery->add();
    }

    /**
     * Get current pdo connection count
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * @param PDO|null $connection
     * @return bool
     */
    private function isConnectionValid(PDO|null $connection): bool
    {
        // return $connection instanceof PDO && $connection->getAttribute(PDO::ATTR_CONNECTION_STATUS) === 'Connection OK';
        try {
            $result = $connection->query('SELECT 1');
            return (bool) $result;
        }
        catch (\Exception $exception)
        {
            return false;
        }

    }

    /**
     * Get the number of idle connections
     * @return int
     */
    public function getIdleCount(): int
    {
        return $this->pool->length();
    }


    public function close():bool
    {
        $connection = $this->pool->pop($this->timeout);
        if ($connection !== false) {
            $this->connector->disconnect($connection);
        }

        $this->closed = true;
        $this->pool->close();
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }
}