<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use SplFileInfo;

/**
 * Recursive copy handler
 * 
 * This wants to be factored out.
 */
class RecursiveCopy
{
    private string $sourceDir;
    private string $targetDir;

    /**
     * @var array<string>
     */
    private array $filter = [
        '.',
        '..'
    ];
 
    /**
     * Create a recursive copy operation pending execution
     * 
     * @param string $sourceDir Where to copy from
     * @param string $targetDir Where to copy to
     * @param array<string> $filter Files or dirs to ignore, including subdir content.
     */
    public function __construct(string $sourceDir, string $targetDir, array $filter = [])
    {
        $this->sourceDir = $sourceDir;
        $this->targetDir = $targetDir;
        $this->filter = array_unique(array_merge($filter, $this->filter));
    }

    /**
     * Run a recursive copy
     */
    public function copy(): void
    {
        if (!file_exists($this->targetDir)) {
            // TODO: Exception if fails
            mkdir($this->targetDir, 0777, true);
        }
        $this->copyLevel($this->sourceDir, $this->targetDir, $this->filter);        
    }

    /**
     * Internal recursion function for copy()
     * 
     * @param string $sourceDir Where to copy from
     * @param string $targetDir Where to copy to
     * @param array<string> $filter Files or dirs to ignore, including subdir content.
     */
    private function copyLevel(string $sourceDir, string $targetDir, array $filter): void
    {
        // TODO LOG DEBUG sprintf("DIR ITERATE: %s to %s\n", $sourceDir, $targetDir);
        $iterator = new FilesystemIterator($sourceDir);
        foreach ($iterator as $splFileInfo) {
            if (!($splFileInfo instanceof SplFileInfo)) {
                continue;
            }
            $basename = $splFileInfo->getBasename();
            if (in_array($basename, $this->filter)) {
                continue;
            }
            if ($splFileInfo->isFile() || $splFileInfo->isLink()) {
                // How to handle links that are dirs...
                $targetFile = $targetDir . '/' . $iterator->getBasename();
                // TODO LOG DEBUG sprintf("FILE: %s to %s\n", $iterator->getPathname(), $targetFile);
                // TODO: Exception if fails
                copy($iterator->getPathname(), $targetFile);
            }
            if ($splFileInfo->isDir()) {
                $targetSubDir =  $targetDir . '/' . $iterator->getBasename();
                // TODO LOG DEBUG sprintf("DIR CREATE: %s to %s\n", $iterator->getPathname(), $targetSubDir);
                // TODO: Exception if fails
                if (!\file_exists($targetSubDir)) {
                    mkdir($targetSubDir);
                }
                $this->copyLevel($iterator->getPathname(), $targetSubDir, $filter);
            }
        }
    }

}