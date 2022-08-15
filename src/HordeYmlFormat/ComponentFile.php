<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

/**
 * Represents a .horde.yml file as found in a project root dir.
 *
 * Partly ported from horde/components
 * - Component/Helper/Composer
 * - Component/Wrapper/HordeYml
 * - Component/Wrapper/ComposerJson
 *
 * The .horde.yml file format is documented here:
 *
 * @link https://wiki.horde.org/Doc/Dev/HordeYmlFormat
 */
class ComponentFile
{
    private Authors $authors;
    private string $homepage;
    private string $mailinglist;
    private string $name;
    private Autoload $autoload;
    private string $type;
    private Version $version;
    private string $description;
    private string $detailedDescription;
    private string $vendor;

    /**
     * Read PEAR-style dependencies
     */
    private DependencyList $dependencies;
    public function __construct(string $name, ?DependencyList $dependencies, ?Version $version, ?Authors $authors, ?Autoload $autoload, string $type = 'library', string $vendor = 'horde', string $detailedDescription = '', string $description = '', string $homepage = '', string $mailinglist = '')
    {
        $this->name = $name;
        $this->autoload = new Autoload();
        $this->authors = $authors ?? new Authors();
        $this->autoload = $autoload ?? new Autoload();
        $this->homepage = $homepage;
        $this->mailinglist = $mailinglist;
        $this->dependencies = $dependencies ?? new DependencyList();
        $this->description = $description;
        $this->detailedDescription = $detailedDescription;
        $this->type = $type;
        $this->vendor = $vendor;
        $this->version = $version ?? new Version('0.0.1');
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): Version
    {
        return $this->version;
    }
    public function getAutoload(): Autoload
    {
        return $this->autoload;
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function getMailingList(): string
    {
        return $this->mailinglist;
    }

    public function getHomepage(): string
    {
        return $this->homepage;
    }

    public function getDetailedDescription(): string
    {
        return $this->detailedDescription;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function getVendor(): string
    {
        return $this->vendor;
    }
    public function getAuthors(): Authors
    {
        return $this->authors;
    }
    public function getDependencies(): DependencyList
    {
        return $this->dependencies;
    }
}
