<?php

declare(strict_types=1);

namespace Horde\Composer;

use DirectoryIterator;
use Composer\Util\Filesystem;

class PresetHandler
{
    private string $presetDir;
    private string $configDir;
    private Filesystem $filesystem;

    public function __construct(string $rootDir, Filesystem $filesystem)
    {
        $this->presetDir = $rootDir . '/presets';
        $this->configDir = $rootDir . '/var/config';
        $this->filesystem = $filesystem;
    }
    public function handle(): void
    {
        // If a deployment has a preset dir copy files from preset
        if (!is_dir($this->presetDir)) {
            return;
        }
        // TODO: Do we need a RecursiveDirectoryInterator here?
        $presetDirIterator = new DirectoryIterator($this->presetDir);
        foreach ($presetDirIterator as $presetAppDir) {
            if (!$presetAppDir->isDir() || $presetAppDir->isDot()) {
                continue;
            }
            $app = $presetAppDir->getFilename();
            $configAppDir = $this->configDir . '/' . $app;
            // ensure the corresponding configAppDir exists
            $this->filesystem->ensureDirectoryExists($configAppDir);
            // Create an iterator for the presetAppDir
            $appDirIterator = new DirectoryIterator($presetAppDir->getPathname());
            foreach ($appDirIterator as $configFile) {
                if (!$configFile->isFile()) {
                    continue;
                }
                $targetFileName = $configAppDir . '/' . $configFile->getFilename();
                // Ensure not to overwrite anything
                if (file_exists($targetFileName)) {
                    continue;
                }
                copy($configFile->getPathname(), $targetFileName);
            }
        }
    }
}
