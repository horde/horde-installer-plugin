<?php

declare(strict_types=1);

namespace Horde\Composer\HordeYmlFormat;

class DependencyList
{
    /**
     * @var Dependency[]
     */
    private iterable $dependencies;

    public function __construct(Dependency ...$dependencies)
    {
        $this->dependencies = $dependencies;
    }

    /**
     * @return Dependency[]
     */
    public function getDependencies(): iterable
    {
        return $this->dependencies;
    }

    /**
     * @return PearDependency[]
     */
    public function getPearDependencies(): iterable
    {
        return array_filter((array)$this->dependencies, fn ($dep) => $dep instanceof PearDependency);
    }

    /**
     * @return ComposerDependency[]
     */
    public function getComposerDependencies(): iterable
    {
        return array_filter((array)$this->dependencies, fn ($dep) => $dep instanceof ComposerDependency);
    }

    /**
     * @return PlatformDependency[]
     */
    public function getPlatformDependencies(): iterable
    {
        return array_filter((array)$this->dependencies, fn ($dep) => $dep instanceof PlatformDependency);
    }
}
