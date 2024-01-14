<?php

namespace App\Response;

class BaseResponse
{

    /**
     * @return static
     */
    public static function make(): static
    {
        return new self();
    }

    /**
     * Response process result status
     *
     * @var bool
     */
    public bool $status = true;

    /**
     * Convert response instance to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [];
    }

    /**
     * Convert response instance to json
     *
     * @return bool|string
     */
    public function json(): bool|string
    {
        return json_encode($this->toArray());
    }
}