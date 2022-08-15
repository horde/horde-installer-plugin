<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

/**
 * A Horde Yaml File's state section with release and API stability
 *
 * Missing API stability is derived from release stability
 */
class State
{
    private string $release;
    private string $api;

    public function __construct(string $release, ?string $api)
    {
        $this->release = $release;
        $this->api = $api ?? $release;
    }

    public function getReleaseStability(): string
    {
        return $this->release;
    }

    public function getApiStability(): string
    {
        return $this->api;
    }
}
