<?php

namespace App;

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use Throwable;


class RequestSender
{

    /**
     * The item show application is sending http request or not
     *
     * @var WaitGroup
     */
    public WaitGroup  $isRunning ;

    /**
     * The starting time of the application
     *
     * @var float
     */
    public  float $startTime ;

    /**
     * The uri (endpoint) address to send request
     *
     * @var ?string
     */
    private ?string $endpoint = null;

    /**
     * The item shown how many request must be sending to server
     *
     * @var int
     */
    private int $requestCount;

    /**
     * The client http connection pool to manage client instance in same time
     *
     * @var HttpClientPool
     */
    public HttpClientPool $httpConnectionPool;


    /**
     * Http request sender constructor
     *
     * @throws Throwable Throwable handle error in sending request
     */
    private function __construct()
    {
        $this->httpConnectionPool = new HttpClientPool();
    }

    /**
     * Create new instance of the request sender statically
     *
     * @return static
     */
    public static function create(): static
    {
        $requestSender = new self();
        $requestSender->startTime = microtime(true);
        $requestSender->isRunning = new WaitGroup();
        return $requestSender;
    }

    /**
     * @param string $host Http clients send request to this host address
     * @param int $port Http client send request to this port number in related host
     * @return $this
     */
    public function setHost(string $host , int $port = 80): static
    {
        $this->httpConnectionPool->setHost($host);
        $this->httpConnectionPool->setPort($port);
        return $this;
    }

    /**
     * @param int $requestCount The total request must be sent
     * @return $this
     */
    public function requestCount(int $requestCount = 1): static
    {
        $this->requestCount = $requestCount;
        return $this;
    }

    /**
     * @param string|null $uri The uri or endpoint address to send http requests
     * @return $this
     */
    public function setUri(?string $uri = null): static
    {
        $this->endpoint = $uri;
        return $this;
    }

    /**
     * @param int $poolSize
     * @param ?int $connectionCount
     * @return $this
     */
    public function setPoolConfig(int $poolSize = 1 , ?int $connectionCount = null): static
    {
        $this->httpConnectionPool->setPoolSize($poolSize);
        $this->httpConnectionPool->setConnectionCount(
            ceil($poolSize / 2)
        );
        return $this;
    }


    /**
     * Start sending async request at the same time
     *
     * @throws Throwable
     */
    public function start(): static
    {
        for ($requestId = 1 ; $requestId<= $this->requestCount; $requestId++)
        {
            $this->isRunning->add(1);
            $httpClient = $this->httpConnectionPool->getConnection();
            \Swoole\Coroutine::create(function () use ($httpClient , $requestId){
               $this->sendRequest($httpClient , $requestId);
            });
        }

        $this->completeAndShowResult();
        return $this;
    }

    /**
     * Get a http client connection from related pool and send request then release
     * the http client connection in the related pool
     *
     * @param Client $httpClient Http client connection instance use in sending request
     * @param int|null $requestId Optional request id or number send as request body
     *
     * @return void
     */
    public function sendRequest(Client $httpClient ,int|null $requestId = null): void
    {
        try {
            $params = http_build_query(['request_id' , $requestId]);

            $httpClient->get($this->endpoint.'?'.$params);
            $response = $httpClient->body;
            if ($response)
                echo "#$requestId:✅\t ";
            else
                echo "#$requestId:❌\t ";
        }
        catch (Throwable $throwable) {
            $this->log($throwable->getMessage() , 'error');
            echo "#$requestId:❌\t ";
        }

        finally {
            $this->isRunning->done();
            $this->httpConnectionPool->releaseConnection($httpClient);
        }
    }

    /**
     * Show console message
     *
     * @param string|null $message
     * @param string $type
     * @return void
     */
    public function log(string|null $message = null , string $type = 'success'): void
    {
        $type == 'success'
            ? $output = "\033[32m {$message}\033[0m" : $output = "\033[31m {$message}\033[0m";
        echo PHP_EOL . $output . PHP_EOL;
    }

    /**
     * Show result and statics of the request sender
     *
     * @return static
     */
    public function completeAndShowResult(): static
    {
        $this->isRunning->wait();
        $this->log("Request Count : {$this->requestCount}");
        $this->log("Progress Duration : {$this->requestCount}");
        return $this;
    }

}
