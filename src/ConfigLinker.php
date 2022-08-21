<?php

declare(strict_types=1);

namespace Horde\Composer;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConfigLinker
{
    private string $baseDir;
    private string $configDir;
    private string $vendorDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
        $this->vendorDir = $baseDir . '/vendor';
        $this->configDir = $this->baseDir . '/var/config';
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
            $targetDir = $this->vendorDir . '/horde//' . $app . '/config';
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
                    mkdir($subPath, 0770, true);
                }
                $linkName = $targetDir . '/' . $relativeName;
                $sourceName = $appConfigDir . '/' . $relativeName;
                if (file_exists($linkName)) {
                    continue;
                }
                symlink($sourceName, $linkName);
            }
            // Do not overwrite existing files or links
        }
    }
}
