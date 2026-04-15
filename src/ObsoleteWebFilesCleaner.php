<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\IO\IOInterface;
use DirectoryIterator;
use Horde\Composer\IOAdapter\FlowIoInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;

/**
 * Cleans up obsolete PHP files from web directories.
 *
 * When apps upgrade from exposed endpoint files to routes, old PHP files
 * in web/$app/ may no longer have corresponding source files in vendor/.
 * This class identifies and removes such orphaned files.
 */
class ObsoleteWebFilesCleaner
{
    private string $vendorDir;
    private string $webDir;
    private IOInterface|FlowIoInterface|null $io = null;
    /**
     * List of packages considered as apps
     *
     * @var string[]
     */
    private array $appPackages;

    /**
     * @param string[] $appPackages List of vendor/app strings
     * @param string   $baseDir     Root package dir
     * @param IOInterface|FlowIoInterface|null $io IO interface for output
     */
    public function __construct(array $appPackages, string $baseDir, IOInterface|FlowIoInterface|null $io = null)
    {
        $this->appPackages = $appPackages;
        $this->vendorDir = $baseDir . '/vendor';
        $this->webDir = $baseDir . '/web';
        $this->io = $io;
    }

    /**
     * Remove obsolete PHP files from web directories
     *
     * For each app, traverse web/$app/ and check if each .php file
     * has a corresponding file in vendor/$vendor/$app/. If not, delete it.
     *
     * @return void
     */
    public function run(): void
    {
        if (!is_dir($this->webDir) || !is_readable($this->webDir)) {
            return;
        }

        foreach ($this->appPackages as $app) {
            if ($app === 'horde/components') {
                continue;
            }

            [$vendor, $appName] = explode('/', $app);
            $appWebDir = $this->webDir . '/' . $appName;
            $appVendorDir = $this->vendorDir . '/' . $app;

            // Skip if web dir doesn't exist
            if (!is_dir($appWebDir) || !is_readable($appWebDir)) {
                continue;
            }

            // Skip if vendor dir doesn't exist (app may be uninstalled)
            if (!is_dir($appVendorDir) || !is_readable($appVendorDir)) {
                continue;
            }

            $this->cleanAppDirectory($appName, $appWebDir, $appVendorDir);
        }
    }

    /**
     * Clean a single app's web directory
     *
     * @param string $appName      Application name
     * @param string $appWebDir    Path to web/$app
     * @param string $appVendorDir Path to vendor/$vendor/$app
     * @return void
     */
    private function cleanAppDirectory(string $appName, string $appWebDir, string $appVendorDir): void
    {
        // Recursively iterate through web/$app
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($appWebDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
        } catch (Exception $e) {
            // If we can't iterate, skip this app
            return;
        }

        foreach ($iterator as $fileInfo) {
            // Only process .php files
            if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $webFilePath = $fileInfo->getPathname();

            // Calculate relative path from app web dir
            $relativePath = substr($webFilePath, strlen($appWebDir) + 1);

            // Check if corresponding file exists in vendor
            $vendorFilePath = $appVendorDir . '/' . $relativePath;

            // If file exists in vendor (or is a symlink to it), keep it
            if (file_exists($vendorFilePath)) {
                continue;
            }

            // Check if it's a symlink (even if broken)
            if (is_link($webFilePath)) {
                // Broken symlinks should be removed
                $this->removeFile($webFilePath, $appName, $relativePath);
                continue;
            }

            // File exists in web/ but not in vendor/ - it's obsolete
            $this->removeFile($webFilePath, $appName, $relativePath);
        }
    }

    /**
     * Remove a file and log the action
     *
     * @param string $filePath     Full path to file to remove
     * @param string $appName      Application name
     * @param string $relativePath Relative path for logging
     * @return void
     */
    private function removeFile(string $filePath, string $appName, string $relativePath): void
    {
        if (unlink($filePath)) {
            if ($this->io) {
                $message = sprintf(
                    '  <comment>Removed obsolete file:</comment> web/%s/%s',
                    $appName,
                    $relativePath
                );

                if ($this->io instanceof IOInterface) {
                    $this->io->write($message, true);
                } else {
                    $this->io->writeln($message);
                }
            }
        }
    }
}
