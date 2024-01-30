<?php

namespace App\Database;


interface ConnectionPoolInterface
{

    /**
     * Initialize the connection pool
     * @return bool
     */
    public function init(): bool;

    /**
     * Close the connection pool, release the resource of all connections
     * @return bool
     */
    public function close(): bool;

    /**
     * Execute requested query
     *
     * @param array $linkData
     * @return void
     */
    public function saveLinkStatics(array $linkData = []): void;
}