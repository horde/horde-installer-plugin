<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use Directory;
use DirectoryIterator;

/**
 * Look for registry snippets in all app's doc/registry.d folder
 *
 * An installed app may be present in the default registry or it may provide
 * a snippet in its doc/registry.d folder. Otherwise the admin must place the
 * snippet into the var/config/horde/registry.d folder himself.
 *
 * A snippet should never override an existing file
 */
class PackageDocRegistrySnippetHandler
{
    private Filesystem $filesystem;
    private DirectoryTree $tree;
    private string $configRegistryDir;

    /**
     * Constructor
     *
     * @param DirectoryTree $tree
     * @param Filesystem $filesystem
     */
    public function __construct(DirectoryTree $tree, Filesystem $filesystem)
    {
        $this->tree = $tree;
        $this->configRegistryDir = $this->tree->getVarConfigDir() . '/horde/registry.d';
        $this->filesystem = $filesystem;
    }

    /**
     * Scan all packages for a registry snippet
     * 
     * Copy snippets to the horde base app's registry snippet dir
     *
     * @return void
     */
    public function handle(): void
    {
        $this->filesystem->ensureDirectoryExists($this->configRegistryDir);
        foreach ($this->tree->getVendors() as $vendor) {
            $vendorDir = $this->tree->getVendorSpecificDir($vendor);
            foreach ($this->tree->getPackagesByVendor($vendor) as $package) {
                // TODO: Check for a .yml file to ensure it is a valid package
                $sourceDir = $this->tree->getDependencyDir($vendor, $package) .  '/doc/registry.d';
                if (!is_dir($sourceDir) || !is_readable($sourceDir)) {
                    continue;
                }
                $files = new DirectoryIterator($sourceDir);
                foreach ($files as $entry) {
                    if ($files->isFile()) {
                        copy($files->getPathName(), $this->configRegistryDir . '/' . $entry);
                    }
                }
            }
        }
    }
}
