<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

class PearDependency implements Dependency
{
    /**
     * The fully qualified package name
     *
     * i.e. "pear.horde.org/Horde_Date"
     *
     * @var string name
     */
    private string $name;
    private string $class;
    private string $version;

    public function __construct(string $name, string $class, string $version)
    {
        $this->name = $name;
        $this->class = $class;
        $this->version = $version;
    }
    /**
     * can be "php", "composer", "pear"
     */
    public function getType(): string
    {
        return 'pear';
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
