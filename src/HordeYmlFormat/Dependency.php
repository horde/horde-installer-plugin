<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

interface Dependency
{
    /**
     * The fully qualified package name as appropriate for that type
     */
    public function getName(): string;
    /**
     * can be "php", "composer", "pear"
     */
    public function getType(): string;

    /**
     * can be "php", "composer", "pear"
     */
    public function getClass(): string;

    public function getVersion(): string;
}
