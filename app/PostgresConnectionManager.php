<?php

namespace App;

use Josantonius\CliPrinter\CliPrinter;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Coroutine\PostgreSQL;

class PostgresConnectionManager
{
    public Channel $channel;
    private array $connectionConfig;

    private CliPrinter $cliPrinter;

    public function __construct(array $connectionConfig)
    {
        $this->channel = new Channel(64);
        $this->connectionConfig = $connectionConfig;
        $this->initializeConnections();
        $this->cliPrinter = new CliPrinter();

    }

    private function initializeConnections(): void
    {
        Coroutine::create(function (){
            for ($i = 0; $i < 64; $i++) {
                $pg = new PostgreSQL();
                $PGConnection = $pg->connect(
                    $this->buildConnectionString($this->connectionConfig)
                );

                if (!$PGConnection) {
                    $this->cliPrinter->display('critical' , "Failed to connect to PostgreSQL: {$pg->error}");
                }

                $this->channel->push($pg);
            }
        });
    }

    public function saveLinkStatics(PostgreSQL $pg, $requestData = [] , string $tableName = 'links_statics'): void
    {
        $stmt = $pg->prepare("INSERT INTO {$tableName} (os,os_version,browser,browser_version,client_ip,base_url,url_path,full_url,created_at) VALUES ($1, $2, $3,$4,$5,$6,$7,$8,$9)");
        $result = $stmt->execute([
                $requestData['os'],
                $requestData['os_version'],
                $requestData['browser'] ,
                $requestData['browser_version'] ,
                $requestData['client_ip'] ,
                $requestData['base_url'] ,
                $requestData['url_path'] ,
                $requestData['full_url'] ,
                $requestData['created_at']
            ]);

        if (!$result) {
            throw new \RuntimeException("Failed to insert data: {$pg->error}");
        }
    }

    private function buildConnectionString(array $config): string
    {
        return "host={$config['host']};port={$config['port']};dbname={$config['dbname']};user={$config['user']};password={$config['password']}";
    }
}