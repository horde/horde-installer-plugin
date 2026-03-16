<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\IO\IOInterface;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConfigLinker
{
    private string $baseDir;
    private string $configDir;
    private string $vendorDir;
    private string $mode = 'proxy';
    private ?IOInterface $io = null;

    public function __construct(string $baseDir, string $mode = 'proxy', ?IOInterface $io = null)
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
            // Iterate recursively
            $contentInfo = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appConfigDir));
            foreach ($contentInfo as $contentItem) {
                // Don't symlink dirs
                if ($contentItem instanceof RecursiveDirectoryIterator  && $contentItem->isDir()) {
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
                $linkName = $targetDir . '/' . $relativeName;
                $sourceName = $appConfigDir . '/' . $relativeName;

                // Check if link exists (including broken symlinks)
                if (is_link($linkName)) {
                    // Remove broken symlinks before recreating
                    if (!file_exists($linkName)) {
                        if ($this->io && $this->io->isVerbose()) {
                            $this->io->write(sprintf(
                                '  <comment>Removing broken symlink:</comment> %s',
                                str_replace($this->vendorDir . '/', 'vendor/', $linkName),
                            ));
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
}
