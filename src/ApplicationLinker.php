<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\InstalledVersions;
use Composer\Util\Filesystem;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ApplicationLinker
{
    private string $baseDir;
    private string $mode;
    private Filesystem $filesystem;
    /**
     * List of packages considered as apps
     *
     * @var string[]
     */
    private array $appPackages;

    /**
     * @param Filesystem $filesystem Filesystem helper
     * @param string[] $appPackages List of vendor/app strings
     * @param string   $baseDir root package dir
     * @param string   $mode    Defaults to symlink
     */
    public function __construct(Filesystem $filesystem, array $appPackages, string $baseDir, string $mode = 'symlink')
    {
        $this->baseDir = $baseDir;
        $this->filesystem = $filesystem;
        $this->appPackages = $appPackages;
        $this->mode = $mode;
    }
    /**
     * Symlink contents of applications to web dir
     *
     * We always check the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        $webDir = $this->baseDir . '/web';
        // Custom vendor dir is not currently supported.
        $vendorDir = $this->baseDir . '/vendor';
        // Ensure we have a webdir
        $this->filesystem->ensureDirectoryExists($webDir);
        // Ensure we have a static dir for ephemeral, generated files ...
        $this->filesystem->ensureDirectoryExists($webDir . '/static');

        foreach ($this->appPackages as $app) {
            if ($app === 'horde/components') {
                continue;
            }
            $appVendorDir = $vendorDir . '/' . $app;
            [$vendor, $appName] = explode('/', $app);
            $appWebDir = $webDir . '/' .  $appName;
            // abort if the app isn't actually there
            if (!is_dir($appVendorDir) || !is_readable($appVendorDir)) {
                // TODO: Consume IO object and warn
                continue;
            }
            // create the app's main dir in the web/ tree
            $this->filesystem->ensureDirectoryExists($appWebDir);
            if ($this->mode === 'symlink') {
                // create links to the app's subdirs and files in the web/ tree, omitting select dirs and files
                foreach (new DirectoryIterator($appVendorDir) as $appFileInfo) {
                    if ($appFileInfo->isDot()) {
                        continue;
                    }
                    $name = $appFileInfo->getFilename();
                    if ($appFileInfo->isDir()) {
                        if (in_array(
                            $name,
                            [
                                'doc', 'test',
                                'bin', 'script',
                                'scripts', 'static', // static should be ensured to exist in webdir.
                                '.git', '.github',
                            ]
                        )) {
                            continue;
                        }
                        $this->filesystem->relativeSymlink(
                            $appVendorDir . '/' . $name,
                            $appWebDir . '/' . $name
                        );
                    }
                    if (in_array(
                        $name,
                        [
                            'LICENSE', 'composer.json', 'composer.lock', '.gitattributes',
                            '.horde.yml', '.travis.yml', 'package.xml', 'phpunit.xml.dist',
                            '.gitignore', 'README.rst',
                        ]
                    )) {
                        continue;
                    }
                    $this->filesystem->relativeSymlink(
                        $appVendorDir . '/' . $name,
                        $appWebDir . '/' . $name
                    );
                }
            }
        }
    }
}
