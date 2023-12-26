<?php

namespace App;
use UAParser\Parser;
use UAParser\Result\Client;


class UserAgent
{
    private Client $components;
    public ?string $originalUserAgent = null;
    public ?string $agent = null;
    public ?string $agentFamily = null;
    public ?string $agentMajor = null;
    public ?string $agentMinor = null;
    public ?string $agentPatch = null;
    public ?string $agentVersion = null;
    public ?string $os = null;
    public ?string $osFamily = null;
    public ?string $osMajor = null;
    public ?string $osMinor = null;
    public ?string $osPatch = null;
    public ?string $osVersion = null;


    public function __construct(?string $userAgent = null)
    {
        $parser = Parser::create();
        $this->components = $parser->parse($userAgent ?: '');
    }

    /**
     * Create a new instance of the userAgent statically
     *
     * @param ?string $userAgent
     * @return $this
     */
    public static function create(?string $userAgent = null): static
    {
        $userAgentInstance = new self($userAgent);
        $userAgentInstance->init();
        return $userAgentInstance;
    }

    /**
     * @return $this
     */
    public function init(): static
    {
        $this->originalUserAgent = $this->components->originalUserAgent;
        $this->initBrowser();
        $this->initOperationSystem();
        return $this;
    }

    /**
     * Initial browser base on the user agent
     *
     * @return $this
     */
    public function initBrowser(): static
    {
        $this->agentFamily =  $this->components->ua->family;
        $this->agentMajor = $this->components->ua->major;
        $this->agentMinor =  $this->components->ua->minor;
        $this->agentPatch =  $this->components->ua->patch;
        $this->agent =  $this->components->ua->toString();
        $this->agentVersion =  $this->components->ua->toVersion();
        return $this;
    }

    /**
     * Initial operating system details base on the user agents
     *
     * @return $this
     */
    public function initOperationSystem(): static
    {
        $this->osFamily =  $this->components->os->family;
        $this->osMajor = $this->components->os->major;
        $this->osMinor =  $this->components->os->minor;
        $this->osPatch =  $this->components->os->patch;
        $this->os =  $this->components->os->toString();
        $this->osVersion =  $this->components->os->toVersion();
        return $this;
    }
}