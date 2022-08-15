<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

/**
 * A Horde Yaml File's version section with release and API
 */
class Version
{
    private string $release;
    private string $api;

    public function __construct(string $release, string $api = null)
    {
        $this->release = $release;
        $this->api = $api ?? $release;
    }

    public function getReleaseVersion(): string
    {
        return $this->release;
    }

    public function getApiVersion(): string
    {
        return $this->api;
    }
}
