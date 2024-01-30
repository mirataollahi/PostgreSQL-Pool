<?php

namespace App\Database\Connector;


use PDO;
use Swoole\Coroutine\PostgreSQL;


interface ConnectorInterface
{
    /**
     * Connect to the specified Server and returns the connection resource
     * @param array|null $config
     * @return PDO|PostgreSQL
     */
    public function connect(array|null $config = null):PDO|PostgreSQL;

    /**
     * Disconnect and free resources
     * @param PDO|PostgreSQL $connection
     * @return void
     */
    public function disconnect(PDO|PostgreSQL $connection):void;

    /**
     * Whether the connection is established
     * @param mixed $connection
     * @return bool
     */
    public function isConnected(PDO|PostgreSQL $connection): bool;

    /**
     * Reset the connection
     * @param PDO|PostgreSQL $connection
     * @return void
     */
    public function reset(PDO|PostgreSQL $connection):void;

    /**
     * Validate the connection
     *
     * @param PDO|PostgreSQL $connection
     * @return bool
     */
    public function validate(PDO|PostgreSQL $connection): bool;


}