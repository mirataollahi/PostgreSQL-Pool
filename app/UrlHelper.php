<?php

namespace App;


class UrlHelper
{
    private const DEFAULT_PORT_HTTP = 80;
    private const DEFAULT_PORT_HTTPS = 443;


    /**
     * @var string The scheme (e.g., "http", "https").
     */
    public string $scheme;

    /**
     * @var string|null The host or domain name.
     */
    public ?string $host;

    /**
     * @var int The port number.
     */
    public int $port;

    /**
     * @var string|null The user part of the URL.
     */
    public ?string $user;

    /**
     * @var string|null The password part of the URL.
     */
    public ?string $pass;

    /**
     * @var string|null The path part of the URL.
     */
    public ?string $path;

    /**
     * @var string|null The query string part of the URL.
     */
    public ?string $query;

    /**
     * @var string|null The fragment or anchor part of the URL.
     */
    public ?string $fragment;

    /**
     * @var string A protected property to store the original URL.
     */
    protected string $original;

    public function __construct(?string $parsed_url = null)
    {
        $this->init($parsed_url ?: '');
    }

    /**
     * Convert the URL components of the given URL string into an associative array.
     *
     * @param ?string $url The URL string to be converted into an array.
     *
     * @return array An associative array containing URL components.
     */
    public static function toArray(?string $url = null): array
    {
        $urlParser = new self($url);
        $port = ($urlParser->port !== self::DEFAULT_PORT_HTTP && $urlParser->port !== self::DEFAULT_PORT_HTTPS) ? ':' . $urlParser->port : '';
        $pass = isset($urlParser->pass) ? ':' . $urlParser->pass : '';
        $pass = ($urlParser->user || $pass) ? "$pass@" : '';
        $trimmedUrl = trim(($urlParser->host ?? null) ?: ($urlParser->path ?? null), " \t\n\r\0\x0B/‌‍");
        $urlPath = strtolower(trim(
            empty(($urlParser->host ?? null)) ? '' : ($urlParser->path ?? null), " \t\n\r\0\x0B/‌‍"
        ));

        return [
            'pure_url' => $url ,
            'scheme' => ($urlParser->scheme ?? 'http://') ,
            'user' => $urlParser->user ?? '',
            'pass' => $pass,
            'host' => $urlParser->host ?? '',
            'port' => $port,
            'path' => $urlParser->path ?? '',
            'query' => $urlParser->query ? '?' . $urlParser->query : '',
            'fragment' => $urlParser->fragment ? '#' . $urlParser->fragment : '',
            'trimmed_url' => $trimmedUrl ,
            'base_url' => strtolower($trimmedUrl) ,
            'url_path' => $urlPath ,
        ];
    }

    /**
     * Initialize the UrlHelper object with parsed URL components.
     *
     * @param string $url The URL to be parsed and used to initialize the object.
     *
     * @return void
     */
    public function init(string $url): void
    {
        $this->original = $url;
        $urlComponents = parse_url(rawurldecode($url));
        $this->scheme = ($urlComponents['scheme'] ?? 'http') . '://';
        $this->host = $urlComponents['host'] ?? null;
        $this->port = (int)($urlComponents['port'] ?? 80);
        $this->user = $urlComponents['user'] ?? null;
        $this->pass = $urlComponents['pass'] ?? null;
        $this->path = $urlComponents['path'] ?? null;
        $this->query = $urlComponents['query'] ?? null;
        $this->fragment = $urlComponents['fragment'] ?? null;
    }

    /**
     * Trim the given URL, extract its components, and return the normalized and lowercase result.
     *
     * @param string $url The input URL to be trimmed and processed.
     *
     * @return string The trimmed and normalized URL.
     */
    public static function trimUrl(string $url): string
    {
        $un_parse_url = UrlHelper::toArray(mb_substr($url, 0, 1000));
        $trimmed_url = trim($un_parse_url['host'] ?: $un_parse_url['path'], " \t\n\r\0\x0B/‌‍");
        return strtolower(preg_replace('#^www\.(.+\.)#i', '$1', $trimmed_url));
    }
}