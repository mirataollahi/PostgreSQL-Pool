<?php

namespace App;

use Josantonius\CliPrinter\CliPrinter;

class CliLogger extends CliPrinter
{
    public bool $isMonitoringEnable = true;
    public bool $isLoggerEnable = true;
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Is application logger enable or not base on .env file
     *
     * @return bool
     */
    public function isLoggerEnable(): bool
    {
        return Config::get('IS_PRODUCTION') === 'false' && $this->isLoggerEnable;
    }

    /**
     * Check is monitoring enable or not
     *
     * @return bool
     */
    public function isMonitoringEnable(): bool
    {
        return Config::get('STATUS_MONITORING') === 'true' && $this->isMonitoringEnable;
    }

    /**
     * Display a text in command line mode
     * If IS_PRODUCTION is enabled in .env file , the method do not showing anything
     *
     * @param string $tagName
     * @param string $message
     * @param array $params
     * @return CliPrinter
     */
    public function display(string $tagName, string $message, array $params = []): CliPrinter
    {
        return $this->isLoggerEnable()
            ? parent::display($tagName, $message, $params) : $this;
    }

    /**
     * Clear command line screen
     *
     * @return void
     */
    public function clearCommandLine(): void
    {
        strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? system('cls') : system('clear');
    }

    public function showApplicationStatus(Application &$application): void
    {
        if ($this->isMonitoringEnable())
        {
            while (true)
            {
                $this->clearCommandLine();
                $blueColor = "\033[34m"; // Blue
                $greenColor = "\033[32m"; // Green
                $resetColor = "\033[0m"; // Reset color
                echo $blueColor . "Received Messages: " . $resetColor . $greenColor . $application->receivedMessages->get() . $resetColor . PHP_EOL;
                echo $blueColor . "Current Client Connections: " . $resetColor . $greenColor . $application->currentClients->get() . $resetColor . PHP_EOL;
                echo $blueColor . "All Connected Clients: " . $resetColor . $greenColor . $application->allConnectedClients->get() . $resetColor . PHP_EOL;
                echo $blueColor . "All Closed Client Connections: " . $resetColor . $greenColor . $application->allClosedClient->get() . $resetColor . PHP_EOL;
                sleep(0.5);
            }
        }
    }
}