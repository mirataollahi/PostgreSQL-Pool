<?php

namespace App\Database;


use App\Core\Config;
use App\Database\Connector\ConnectorInterface;
use App\Logger\CliLogger;
use Josantonius\CliPrinter\CliPrinter;
use PDO;
use RuntimeException;
use Swoole\Atomic;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use WeakMap;

class AdvanceConnectionPool implements ConnectionPoolInterface
{
    /**@var float The timeout of the operation channel */
    const CHANNEL_TIMEOUT = 0.001;

    /**@var float The minimum interval to check the idle connections */
    const MIN_CHECK_IDLE_INTERVAL = 10;

    /**@var WeakMap The last active time of connection */
    protected WeakMap $lastActiveTime;

    /**@var bool Whether the connection pool is initialized */
    protected bool $initialized;

    /**@var bool Whether the connection pool is closed */
    protected bool $closed;

    /**@var Channel The connection pool */
    protected Channel $pool;

    /**@var ConnectorInterface The connector */
    protected ConnectorInterface $connector;

    /**@var int Current all connection count */
    protected int $connectionCount = 0;

    /**@var int The minimum number of active connections */
    protected int $minActive = 16;

    /**@var int The maximum number of active connections */
    protected int $maxActive = 32;

    /**@var float The maximum waiting time for connection, when reached, an exception will be thrown */
    protected float $maxWaitTime = 5;

    /**@var float The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool */
    protected float $maxIdleTime = 5;

    /**@var float The interval to check idle connection */
    protected float $idleCheckInterval = 5;

    /**@var int The timer id of balancer */
    protected int $balancerTimerId;

    public Atomic $allQuery;
    public Atomic $successQuery;
    public Atomic $failQuery;
    public CliPrinter $cliPrinter;

    /**
     * ConnectionPool constructor.
     * int minActive The minimum number of active connections
     * int maxActive The maximum number of active connections
     * float maxWaitTime The maximum waiting time for connection, when reached, an exception will be thrown
     * float maxIdleTime The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool
     * float idleCheckInterval The interval to check idle connection
     * @param ConnectorInterface $connector The connector instance of ConnectorInterface
     */
    public function __construct(ConnectorInterface $connector)
    {
        $this->lastActiveTime = new WeakMap();
        $this->initialized = false;
        $this->closed = false;
        $this->minActive = $poolConfig['minActive'] ?? 20;
        $this->maxActive = $poolConfig['maxActive'] ?? 100;
        $this->maxWaitTime = $poolConfig['maxWaitTime'] ?? 5;
        $this->maxIdleTime = $poolConfig['maxIdleTime'] ?? 30;
        $poolConfig['idleCheckInterval'] = $poolConfig['idleCheckInterval'] ?? 15;
        $this->idleCheckInterval = max($poolConfig['idleCheckInterval'], static::MIN_CHECK_IDLE_INTERVAL);
        $this->connector = $connector;

        $this->allQuery = new Atomic();
        $this->successQuery = new Atomic();
        $this->failQuery = new Atomic();
        $this->cliPrinter = new CliLogger();
    }

    /**
     * Initialize the connection pool
     * @return bool
     */
    public function init(): bool
    {
        if ($this->initialized) {
            return false;
        }
        $this->initialized = true;
        $this->pool = new Channel($this->maxActive);
        $this->balancerTimerId = $this->startBalanceTimer($this->idleCheckInterval);
        Coroutine::create(function () {
            for ($i = 0; $i < $this->minActive; $i++) {
                $connection = $this->createConnection();
                $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
                if ($ret === false) {
                    $this->removeConnection($connection);
                }
            }
        });
        return true;
    }

    /**
     * Borrow a connection from the connection pool, throw an exception if timeout
     * @return mixed The connection resource
     * @throws BorrowConnectionTimeoutException
     * @throws RuntimeException
     */
    public function borrow(): mixed
    {
        if (!$this->initialized) {
            throw new RuntimeException('Please initialize the connection pool first, call $pool->init().');
        }
        if ($this->pool->isEmpty()) {
            // Create more connections
            if ($this->connectionCount < $this->maxActive) {
                return $this->createConnection();
            }
        }

        $connection = $this->pool->pop($this->maxWaitTime);
        if ($connection === false) {
            $exception = new BorrowConnectionTimeoutException(sprintf(
                'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                $this->maxWaitTime,
                $this->pool->length(),
                $this->connectionCount
            ));
            $exception->setTimeout($this->maxWaitTime);
            throw $exception;
        }
        if ($this->connector->isConnected($connection)) {
            // Reset the connection for the connected connection
            $this->connector->reset($connection);
        } else {
            // Remove the disconnected connection, then create a new connection
            $this->removeConnection($connection);
            $connection = $this->createConnection();
        }
        return $connection;
    }

    /**
     * Return a connection to the connection pool
     * @param mixed $connection The connection resource
     * @return bool
     */
    public function return(mixed $connection): bool
    {
        if (!$this->connector->validate($connection)) {
            throw new \RuntimeException('Connection of unexpected type returned.');
        }

        if (!$this->initialized) {
            throw new \RuntimeException('Please initialize the connection pool first, call $pool->init().');
        }
        if ($this->pool->isFull()) {
            // Discard the connection
            $this->removeConnection($connection);
            return false;
        }
        $this->lastActiveTime[$connection] = time();
        $ret = $this->pool->push($connection, static::CHANNEL_TIMEOUT);
        if ($ret === false) {
            $this->removeConnection($connection);
        }
        return true;
    }

    /**
     * Get the number of created connections
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * Get the number of idle connections
     * @return int
     */
    public function getIdleCount(): int
    {
        return $this->pool->length();
    }

    /**
     * Close the connection pool and disconnect all connections
     * @return bool
     */
    public function close(): bool
    {
        if (!$this->initialized) {
            return false;
        }
        if ($this->closed) {
            return false;
        }
        $this->closed = true;
        swoole_timer_clear($this->balancerTimerId);
        Coroutine::create(function () {
            while (true) {
                if ($this->pool->isEmpty()) {
                    break;
                }
                $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
                if ($connection !== false) {
                    $this->connector->disconnect($connection);
                }
            }
            $this->pool->close();
        });
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function startBalanceTimer(float $interval): bool|int
    {
        return swoole_timer_tick(round($interval) * 1000, function () {
            $now = time();
            $validConnections = [];
            while (true) {
                if ($this->closed) {
                    break;
                }
                if ($this->connectionCount <= $this->minActive) {
                    break;
                }
                if ($this->pool->isEmpty()) {
                    break;
                }
                $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
                if ($connection === false) {
                    continue;
                }
                $lastActiveTime = $this->lastActiveTime[$connection] ?? 0;
                if ($now - $lastActiveTime < $this->maxIdleTime) {
                    $validConnections[] = $connection;
                } else {
                    $this->removeConnection($connection);
                }
            }

            foreach ($validConnections as $validConnection) {
                $ret = $this->pool->push($validConnection, static::CHANNEL_TIMEOUT);
                if ($ret === false) {
                    $this->removeConnection($validConnection);
                }
            }
        });
    }

    protected function createConnection(): mixed
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
     * @param array $linkData
     * @return void
     * @throws BorrowConnectionTimeoutException
     */
    public function saveLinkStatics(array $linkData = []): void
    {
        $this->allQuery->add();
        /**@var PDO $connection */
        $pdo = $this->borrow();

        if ($pdo instanceof PDO) {
            try {
                $linksTable = Config::get('LINKS_TABLE');
                $schema = Config::get('DATABASE_SCHEMA');
                $stmt = $pdo->prepare("INSERT INTO $schema.$linksTable (os, os_version, browser, browser_version, client_ip, base_url, url_path, full_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bindParam(1, $linkData['os']);
                $stmt->bindParam(2, $linkData['os_version']);
                $stmt->bindParam(3, $linkData['browser']);
                $stmt->bindParam(4, $linkData['browser_version']);
                $stmt->bindParam(5, $linkData['client_ip']);
                $stmt->bindParam(6, $linkData['base_url']);
                $stmt->bindParam(7, $linkData['url_path']);
                $stmt->bindParam(8, $linkData['full_url']);
                $stmt->bindParam(9, $linkData['created_at']);
                $isSaved = $stmt->execute();
                if ($isSaved) {
                    $this->cliPrinter->display('debug', "Like statics saved");
                    $this->successQuery->add();
                    return;
                }
            } catch (\Exception $exception) {
                $this->cliPrinter->display('critical', $exception->getMessage());
                $this->failQuery->add();
                return;
            } finally {
                $this->return($pdo);
            }
        } else $this->failQuery->add();
    }

}