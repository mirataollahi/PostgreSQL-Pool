<?php

namespace App\Database;
use Exception;
class BorrowConnectionTimeoutException extends Exception
{
    protected float $timeout;

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }
}