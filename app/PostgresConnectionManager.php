<?php

namespace App;

use Swoole\Coroutine\Channel;
use Swoole\Coroutine\PostgreSQL;

class PostgresConnectionManager
{
    public Channel $channel;
    private array $connectionConfig;
    private CliLogger $cliPrinter;


    public function __construct(array $connectionConfig)
    {
        $this->cliPrinter = new CliLogger();
        $this->channel = new Channel(64);
        $this->connectionConfig = $connectionConfig;
    }

    public function initializeConnections(): void
    {

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
    }

    public function saveLinkStatics(PostgreSQL $pg, $requestData = [] , string $tableName = 'links_statics'): void
    {
        try {
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
                $this->cliPrinter->display('critical' , "Failed to insert data: {$pg->error}");
            }
        }
        catch (\Exception $exception)
        {
            $this->cliPrinter->display('critical' , $exception->getMessage());
        }
    }

    private function buildConnectionString(array $config): string
    {
        return "host={$config['host']};port={$config['port']};dbname={$config['dbname']};user={$config['user']};password={$config['password']}";
    }

}