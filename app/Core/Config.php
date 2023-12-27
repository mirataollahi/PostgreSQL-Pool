<?php

namespace App\Core;

use Dotenv\Dotenv;

class Config
{
    public static Dotenv $envDriver;

    /**
     * @return Dotenv
     */
    public static function init(): Dotenv
    {
        if (!isset(static::$envDriver)) {
            $dotenv = Dotenv::createImmutable(BASE_PATH);
            $dotenv->load();
            static::$envDriver = $dotenv;
        }
        return static::$envDriver;
    }

    /**
     * Get a value form .env
     *
     * @param $key
     * @param null $default
     * @return string|array|null
     */
    public static function get($key, $default = null): string|array|null
    {
        static::init();
        return $_ENV[$key] ?? $default;
    }

}