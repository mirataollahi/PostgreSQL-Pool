<?php
namespace App;


use RuntimeException;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\Http\Client;
use Throwable;

class HttpClientPool
{

    /**
     * The host address of the http connections in the pool
     *
     * @var string
     */
    private string $host;

    /**
     * The port number of the http connections in the pool
     *
     * @var int
     */
    private int $port;

    /**
     * The http connection pool driver
     *
     * @var Channel|null
     */
    private ?Channel $pool;

    /**
     * The http connection pool size
     *
     * @var int
     */
    private int $poolSize = 1;

    /**
     * The maximum client http connection in the pool
     *
     * @var int
     */
    private int $connectionCount = 1;

    /**
     * The timeout (seconds) in pop http client from the pool
     *
     * @var float
     */
    private float $timeout;


    /**
     * Create a http connection pool to manage and control concurrent requests
     *
     * @param ?string $host
     * @param int $port
     * @param int $poolSize
     * @param float $timeout
     */
    public function __construct(?string $host = null , int $port = 80 ,int $poolSize = 100 , float $timeout = -1)
    {
        if ($host)
            $this->setHost($host);
        if ($port)
            $this->setPort($port);

        $this->poolSize = $poolSize;
        $this->timeout = $timeout;
        $this->pool = new Channel($this->poolSize);
    }

    /**
     * Set http client connection host address
     *
     * @param string $host
     * @return $this
     */
    public function setHost(string $host): static
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Set http client connection port number
     *
     * @param int|null $port
     * @return $this
     */
    public function setPort(?int $port = 80): static
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Set pool maximum size
     *
     * @param int $poolSize
     * @return $this
     */
    public function setPoolSize(int $poolSize = 1): static
    {
        $this->poolSize = $poolSize;
        return $this;
    }

    /**
     * Set client http count creating in this pool
     *
     * @param int $connectionCount
     * @return $this
     */
    public function setConnectionCount(int $connectionCount = 1): static
    {
        $this->connectionCount = $connectionCount;
        return $this;
    }


    /**
     * Get a http client before sending request
     *
     * @return Client
     * @throws Throwable
     */
    public function getConnection(): Client
    {
        if ($this->pool === null) {
            throw new RuntimeException('Pool has been closed');
        }
        if ($this->pool->isEmpty() && $this->connectionCount < $this->poolSize) {
            $this->make();
        }
        return $this->pool->pop($this->timeout);
    }


    /**
     * release http client after complete request progress
     *
     * @param Client|null $httpConnection
     * @return void
     */
    public function releaseConnection(Client|null $httpConnection): void
    {
        if ($this->pool === null) {
            return;
        }
        if ($httpConnection !== null) {
            $this->pool->push($httpConnection);
        } else {
            /* connection broken */
            $this->connectionCount -= 1;
            $this->make();
        }
    }


    /**
     * Create initial http client connection and add to pool
     *
     * @return $this
     */
    public function fill(): static
    {
        $initialConnectionCount = ceil($this->connectionCount / 2);

        while ($this->poolSize > $initialConnectionCount) {
            $this->make();
        }
        return $this;
    }


    /**
     * Make a http client connection and push in pool
     *
     * @return void
     */
    protected function make(): void
    {
        $this->connectionCount++;

        $this->pool->push(new Client($this->host , $this->port) , $this->timeout);
    }

}