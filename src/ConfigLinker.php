<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\IO\IOInterface;
use DirectoryIterator;
use Horde\Composer\IOAdapter\FlowIoInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConfigLinker
{
    private string $baseDir;
    private string $configDir;
    private string $vendorDir;
    private string $mode = 'proxy';
    private IOInterface|FlowIoInterface|null $io = null;

    public function __construct(string $baseDir, string $mode = 'proxy', IOInterface|FlowIoInterface|null $io = null)
    {
        $this->baseDir = $baseDir;
        $this->vendorDir = $baseDir . '/vendor';
        $this->configDir = $this->baseDir . '/var/config';
        $this->mode = $mode;
        $this->io = $io;
    }
    /**
     * Symlink contents of var/config
     *
     * We always check the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        // Abort unless var/config exists and is readable
        if (!is_dir($this->configDir) || !is_readable($this->configDir)) {
            return;
        }
        // Iterate through subdirs
        foreach (new DirectoryIterator($this->configDir) as $appFileInfo) {
            if (!$appFileInfo->isDir()) {
                continue;
            }
            if ($appFileInfo->isDot()) {
                continue;
            }
            $app = $appFileInfo->getFilename();
            // Next if no corresponding web/$app/config dir exists
            $appConfigDir = $appFileInfo->getPathname();
            // TODO: Make this work for other vendors
            $targetDir = $this->vendorDir . '/horde/' . $app . '/config';
            if (!is_dir($targetDir)) {
                continue;
            }

            // First, clean up orphaned symlinks/copies in vendor
            $this->cleanupVendorConfig($appConfigDir, $targetDir);

            // Then, link current files from var/config to vendor
            $this->linkConfigFiles($appConfigDir, $targetDir);
        }
    }

    /**
     * Clean up orphaned symlinks and copies in vendor config directory
     *
     * Removes symlinks and files that point to non-existent sources in var/config.
     * This handles the case where users delete customization files.
     *
     * @param string $appConfigDir var/config/$app directory
     * @param string $targetDir vendor/$vendor/$app/config directory
     */
    private function cleanupVendorConfig(string $appConfigDir, string $targetDir): void
    {
        $contentInfo = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($contentInfo as $vendorFile) {
            // Only check files, not directories
            if ($vendorFile->isDir()) {
                continue;
            }

            $relativeName = $contentInfo->getSubPathname();

            // Skip .dist files - they're managed by ConfigDistributor
            if (str_ends_with($relativeName, '.dist') || str_ends_with($relativeName, '.dist.php')) {
                continue;
            }

            // Skip hooks.php - it's never symlinked
            if (str_contains($relativeName, 'hooks.php')) {
                continue;
            }

            // Skip registry.php - it's never symlinked (only registry.local.php, registry-*.php work)
            if ($relativeName === 'registry.php') {
                continue;
            }

            $expectedSource = $appConfigDir . '/' . $relativeName;

            // Check if this is a symlink
            if (is_link($vendorFile->getPathname())) {
                // Remove broken symlinks (source doesn't exist)
                if (!file_exists($expectedSource)) {
                    if ($this->io) {
                        $isVerbose = ($this->io instanceof IOInterface) && $this->io->isVerbose();
                        if ($isVerbose) {
                            $message = sprintf(
                                '  <comment>Removing orphaned symlink:</comment> %s',
                                str_replace($this->vendorDir . '/', 'vendor/', $vendorFile->getPathname())
                            );
                            if ($this->io instanceof IOInterface) {
                                $this->io->write($message);
                            } else {
                                $this->io->writeln($message);
                            }
                        }
                    }
                    unlink($vendorFile->getPathname());
                }
            } elseif (in_array($this->mode, ['copy']) && file_exists($vendorFile->getPathname())) {
                // In copy mode, remove copied files if source no longer exists
                if (!file_exists($expectedSource)) {
                    if ($this->io) {
                        $isVerbose = ($this->io instanceof IOInterface) && $this->io->isVerbose();
                        if ($isVerbose) {
                            $message = sprintf(
                                '  <comment>Removing orphaned copy:</comment> %s',
                                str_replace($this->vendorDir . '/', 'vendor/', $vendorFile->getPathname())
                            );
                            if ($this->io instanceof IOInterface) {
                                $this->io->write($message);
                            } else {
                                $this->io->writeln($message);
                            }
                        }
                    }
                    unlink($vendorFile->getPathname());
                }
            }
        }
    }

    /**
     * Link config files from var/config to vendor
     *
     * @param string $appConfigDir var/config/$app directory
     * @param string $targetDir vendor/$vendor/$app/config directory
     */
    private function linkConfigFiles(string $appConfigDir, string $targetDir): void
    {
        // Iterate recursively
        $contentInfo = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appConfigDir));
        foreach ($contentInfo as $contentItem) {
            // Don't symlink dirs
            if ($contentItem instanceof RecursiveDirectoryIterator && $contentItem->isDir()) {
                continue;
            }
            // Generate missing dirs below targetdir
            $relativeName = $contentInfo->getSubPathname();
            $subPath = $targetDir . '/' . $contentInfo->getSubPath();
            if (!is_dir($subPath)) {
                mkdir($subPath, 0o770, true);
            }
            // Hooks don't need to be symlinked. Duplicating them can even confuse the autoloader
            if (is_int(strpos($subPath, 'hooks.php'))) {
                continue;
            }
            // Skip .dist files - they flow from vendor to var, not var to vendor
            if (str_ends_with($relativeName, '.dist') || str_ends_with($relativeName, '.dist.php')) {
                continue;
            }
            // Skip registry.php (not registry.local.php or registry-*.php) - it does nothing
            // Only registry.php.dist, registry.local.php, registry-*.php, and registry.d/*.php are used
            if ($relativeName === 'registry.php') {
                continue;
            }
            $linkName = $targetDir . '/' . $relativeName;
            $sourceName = $appConfigDir . '/' . $relativeName;

            // Check if link exists (including broken symlinks)
            if (is_link($linkName)) {
                // Remove broken symlinks before recreating
                if (!file_exists($linkName)) {
                    if ($this->io) {
                        // Check if verbose output is supported (IOInterface only)
                        $isVerbose = ($this->io instanceof IOInterface) && $this->io->isVerbose();
                        if ($isVerbose) {
                            $message = sprintf(
                                '  <comment>Removing broken symlink:</comment> %s',
                                str_replace($this->vendorDir . '/', 'vendor/', $linkName),
                            );
                            if ($this->io instanceof IOInterface) {
                                $this->io->write($message);
                            } else {
                                $this->io->writeln($message);
                            }
                        }
                    }
                    unlink($linkName);
                } else {
                    // Valid symlink exists, skip
                    continue;
                }
            } elseif (file_exists($linkName)) {
                // Regular file exists, skip
                continue;
            }

            if (in_array($this->mode, ['proxy', 'symlink'])) {
                symlink($sourceName, $linkName);
            } else {
                copy($sourceName, $linkName);
            }
        }
        // Do not overwrite existing files or links
    }
}
