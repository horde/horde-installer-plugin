<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use DirectoryIterator;
use ErrorException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class JsTreeLinker
{
    private Filesystem $filesystem;
    private string $vendorDir;
    private string $webDir;
    private string $jsDir;
    /**
     * List of apps
     *
     * @var string[]
     */
    private array $apps;
    /**
     * List of libraries
     *
     * @var string[]
     */
    private array $libs;
    private string $mode = 'symlink';

    /**
     * Constructor
     *
     * @param Filesystem $filesystem
     * @param string $baseDir
     * @param string[] $apps
     * @param string[] $libs
     */
    public function __construct(
        Filesystem $filesystem,
        string $baseDir,
        array $apps = [],
        array $libs = [],
        string $mode = 'symlink'
    ) {
        $this->filesystem = $filesystem;
        $this->vendorDir = $baseDir . '/vendor';
        $this->webDir= $baseDir . '/web';
        $this->jsDir = $this->webDir . '/js';
        $this->apps = $apps;
        $this->libs = $libs;
        $this->mode = $mode;
    }
    /**
     * Build the web/js/ symlink tree
     *
     * Dependencies of type horde-application or horde-library can have a
     * js dir which needs to be exposed web-readable
     *
     * Traditionally, horde/js contains both the js from horde base package
     * and from libraries while apps have their JS in horde/$app/js
     *
     * In Composer based setup, we build our own symlink structure and
     * tweak registry to the new locations
     *
     * We always build the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        $this->filesystem->ensureDirectoryExists($this->jsDir);
        // app javascript dirs are exposed under js/$app
        foreach ($this->apps as $app) {
            [$vendor, $name] =  explode('/', $app, 2);
            $appPath = $this->webDir . '/' . $name;
            $jsSourcePath = $appPath . '/js';
            if (!$this->filesystem->isReadable($jsSourcePath)) {
                continue;
            }
            $targetDir = $this->jsDir . '/' . $name;
            $this->linkDir($jsSourcePath, $targetDir);
        }
        // Library javascript dirs are exposed under js/horde/
        foreach ($this->libs as $lib) {
            [$vendor, $name] =  explode('/', $lib, 2);
            $libraryPath = $this->vendorDir . '/'. $vendor . '/' . $name;
            $jsSourcePath = $libraryPath . '/js';
            if (!$this->filesystem->isReadable($jsSourcePath)) {
                continue;
            }
            $targetDir = $this->jsDir . '/horde';
            $this->linkDir($jsSourcePath, $targetDir);
        }
    }

    // Link all files and subdirs from source dir to target dir
    public function linkDir(string $sourceDir, string $targetDir): void
    {
        $this->filesystem->ensureDirectoryExists($targetDir);
        try {
            $sourceDirHandle = opendir($sourceDir);
            if ($sourceDirHandle === false) {
                return;
            }
        } catch (ErrorException $errorException) {
            return;
        }
        while (false !== ($sourceItem = readdir($sourceDirHandle))) {
            if ($sourceItem == '.' || $sourceItem == '..') {
                continue;
            }
            $sourceFile = $sourceDir . '/' . $sourceItem;
            $targetFile = $targetDir . '/' . $sourceItem;
            if ($this->mode === 'symlink') {
                $this->filesystem->relativeSymlink($sourceFile, $targetFile);
            } else {
                copy($sourceFile, $targetFile);
            }
        }
        closedir($sourceDirHandle);
    }
}
