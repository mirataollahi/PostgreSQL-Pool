<?php

namespace App;

use Josantonius\CliPrinter\CliPrinter;

class CliLogger extends CliPrinter
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $tagName
     * @param string $message
     * @param array $params
     * @return CliPrinter
     */
    public function display(string $tagName, string $message, array $params = []): CliPrinter
    {
        if (Config::get('IS_PRODUCTION') === 'false')
            return parent::display($tagName, $message, $params);
        return $this;
    }
}