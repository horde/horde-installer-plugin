<?php

declare(strict_types=1);

namespace Horde\Composer;

use Horde\Composer\IOAdapter\FlowIoInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;

/**
 * Cleans up dead (broken) symlinks from web directories.
 *
 * Scans web/js/ and web/themes/ directories recursively for symlinks
 * that point to non-existent targets and removes them.
 *
 * This helps keep the web directories clean after packages are uninstalled
 * or reorganized, preventing 404 errors and broken references.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */
class DeadSymlinkCleaner
{
    private string $webDir;
    private ?FlowIoInterface $io = null;

    /**
     * Directories to scan for dead symlinks (relative to web/)
     *
     * @var string[]
     */
    private array $scanDirs = ['js', 'themes'];

    /**
     * @param string                  $baseDir Root package dir
     * @param FlowIoInterface|null    $io      IO interface for output
     */
    public function __construct(string $baseDir, ?FlowIoInterface $io = null)
    {
        $this->webDir = $baseDir . '/web';
        $this->io = $io;
    }

    /**
     * Scan and remove all dead symlinks in web/js/ and web/themes/
     *
     * @return int Number of dead symlinks removed
     */
    public function run(): int
    {
        if (!is_dir($this->webDir) || !is_readable($this->webDir)) {
            return 0;
        }

        $totalRemoved = 0;

        foreach ($this->scanDirs as $dir) {
            $fullPath = $this->webDir . '/' . $dir;

            if (!is_dir($fullPath) || !is_readable($fullPath)) {
                continue;
            }

            $removed = $this->cleanDirectory($dir, $fullPath);
            $totalRemoved += $removed;
        }

        if ($totalRemoved > 0 && $this->io) {
            $this->io->writeln(sprintf(
                '<info>Removed %d dead symlink%s from web/ directories</info>',
                $totalRemoved,
                $totalRemoved === 1 ? '' : 's'
            ));
        }

        return $totalRemoved;
    }

    /**
     * Clean a single directory recursively
     *
     * @param string $dirName  Directory name for logging (relative to web/)
     * @param string $fullPath Full path to directory
     * @return int Number of symlinks removed
     */
    private function cleanDirectory(string $dirName, string $fullPath): int
    {
        $removed = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
        } catch (Exception $e) {
            // If we can't iterate, skip this directory
            if ($this->io) {
                $this->io->writeln(sprintf(
                    '<comment>Warning: Could not scan %s: %s</comment>',
                    $dirName,
                    $e->getMessage()
                ));
            }
            return 0;
        }

        foreach ($iterator as $fileInfo) {
            $path = $fileInfo->getPathname();

            // Check if it's a symlink
            if (!is_link($path)) {
                continue;
            }

            // Check if the symlink target exists
            // Note: file_exists() follows symlinks, so it returns false for broken symlinks
            if (file_exists($path)) {
                continue;
            }

            // This is a dead symlink - remove it
            $relativePath = substr($path, strlen($this->webDir) + 1);

            if ($this->removeSymlink($path, $relativePath)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Remove a symlink and log the action
     *
     * @param string $path         Full path to symlink
     * @param string $relativePath Relative path for logging (from web/)
     * @return bool True if successfully removed
     */
    private function removeSymlink(string $path, string $relativePath): bool
    {
        // Get the target for informational purposes (even though it doesn't exist)
        $target = @readlink($path);

        if (@unlink($path)) {
            if ($this->io) {
                $message = sprintf(
                    '  <comment>Removed dead symlink:</comment> web/%s',
                    $relativePath
                );

                if ($target !== false) {
                    $message .= sprintf(' <comment>(pointed to: %s)</comment>', $target);
                }

                $this->io->writeln($message);
            }
            return true;
        }

        return false;
    }

    /**
     * Get list of directories to scan
     *
     * @return string[]
     */
    public function getScanDirs(): array
    {
        return $this->scanDirs;
    }

    /**
     * Set custom directories to scan (relative to web/)
     *
     * @param string[] $dirs Directory names
     * @return self
     */
    public function setScanDirs(array $dirs): self
    {
        $this->scanDirs = $dirs;
        return $this;
    }
}
