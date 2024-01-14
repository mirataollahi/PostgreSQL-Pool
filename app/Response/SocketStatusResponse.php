<?php

namespace App\Response;

use Swoole\Atomic;

class SocketStatusResponse extends BaseResponse
{
    public Atomic $receivedMessages;
    public Atomic $currentClients;
    public Atomic $allConnectedClients;
    public Atomic $allClosedClient;
    public int $connectionCount;


    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status' => true,
            'received_messages' => number_format($this?->receivedMessages->get() ?: 0),
            'current_clients' => number_format($this->currentClients?->get() ?: 0),
            'all_connected_clients' => number_format($this->allConnectedClients?->get() ?: 0),
            'all_closed_client' => number_format($this->allClosedClient?->get() ?: 0),
            'database_connection_count' => $this->connectionCount ,
        ];
    }
}