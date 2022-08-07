<?php

declare(strict_types=1);

namespace Horde\Composer;

use DirectoryIterator;
use Composer\Util\Filesystem;

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
    /**
     * List of installed apps
     *
     * @var string[]
     */
    private array $apps;
    private string $configDir;
    private Filesystem $filesystem;
    private string $webDir;

    /**
     * Constructor
     *
     * @param string $rootDir
     * @param string[] $apps
     */
    public function __construct(string $rootDir, Filesystem $filesystem, array $apps)
    {
        $this->apps = $apps;
        $this->configDir = $rootDir . '/var/config/horde/registry.d';
        $this->filesystem = $filesystem;
        $this->webDir = $rootDir . '/web';
    }

    public function handle(): void
    {
        $this->filesystem->ensureDirectoryExists($this->configDir);
        foreach ($this->apps as $app) {
            list($vendor, $name) = explode('/', $app, 2);
            $sourceDir = $this->webDir . '/' . $name . '/doc/registry.d';
            if (!is_dir($sourceDir)) {
                continue;
            }
            if (!is_readable($sourceDir)) {
                continue;
            }
            $files = new DirectoryIterator($sourceDir);
            foreach ($files as $entry) {
                if ($files->isFile()) {
                    copy($files->getPathName(), $this->configDir . '/' . $entry);
                }
            }
        }
    }
}
