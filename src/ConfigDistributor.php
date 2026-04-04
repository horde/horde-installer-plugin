<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use Horde\Composer\IOAdapter\FlowIoInterface;

/**
 * Distributes config .dist files from vendor packages to var/config
 *
 * Developer-maintained template files (.dist) flow from vendor to var/config.
 * These files are always overwritten to reflect the latest package versions.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */
class ConfigDistributor
{
    /**
     * Standard config files to distribute as .dist templates
     *
     * These files are maintained by app developers and should be
     * distributed as .dist variants to var/config.
     */
    private const CONFIG_FILES = [
        'backends',
        'attributes',
        'prefs',
        'routes',
        'mime_drivers',
        'fields',
        'templates',
        'conf',     // Special: only .dist, never actual conf.php
        'registry', // Special: only .dist, user maintains registry.local.php
    ];

    public function __construct(
        private readonly string $rootPackageDir,
        private readonly string $vendorDir,
        private readonly Filesystem $filesystem,
        private readonly ?FlowIoInterface $io = null,
    ) {}

    /**
     * Distribute config .dist files from vendor to var/config
     *
     * For each installed Horde application, copies config template files
     * from vendor/$vendor/$app/config to var/config/$app/ as .dist files.
     *
     * @param array<string> $hordeApps List of Horde application package names
     */
    public function run(array $hordeApps): void
    {
        foreach ($hordeApps as $appPackage) {
            [$vendor, $app] = explode('/', $appPackage);
            $vendorConfigDir = "{$this->vendorDir}/{$vendor}/{$app}/config";
            $varConfigDir = "{$this->rootPackageDir}/var/config/{$app}";

            // Skip if vendor config dir doesn't exist
            if (!is_dir($vendorConfigDir)) {
                continue;
            }

            // Create var/config/$app only when needed (after we know config files exist)
            $dirCreated = false;

            foreach (self::CONFIG_FILES as $configName) {
                // Check if source file exists before creating target dir
                $sourceDistFile = "{$vendorConfigDir}/{$configName}.php.dist";
                $sourcePhpFile = "{$vendorConfigDir}/{$configName}.php";

                if (file_exists($sourceDistFile) || file_exists($sourcePhpFile)) {
                    // Create dir on first needed file
                    if (!$dirCreated && !is_dir($varConfigDir)) {
                        $this->filesystem->ensureDirectoryExists($varConfigDir);
                        $dirCreated = true;
                    }
                    $this->distributeConfigFile($vendorConfigDir, $varConfigDir, $configName, $app);
                }
            }
        }
    }

    /**
     * Distribute a single config file from vendor to var/config
     *
     * Tries .dist variant first, falls back to .php file.
     * Always overwrites existing files (developer updates must propagate).
     * Assumes target directory already exists.
     *
     * @param string $sourceDir Vendor config directory
     * @param string $targetDir var/config app directory
     * @param string $configName Config file basename (without extension)
     * @param string $app Application name (for logging)
     */
    private function distributeConfigFile(
        string $sourceDir,
        string $targetDir,
        string $configName,
        string $app
    ): void {
        $targetFile = "{$targetDir}/{$configName}.php.dist";

        // Try .dist variant first
        $sourceDistFile = "{$sourceDir}/{$configName}.php.dist";
        if (file_exists($sourceDistFile)) {
            $this->filesystem->copy($sourceDistFile, $targetFile);
            $this->logVerbose("  <info>Distributed {$app}/{$configName}.php.dist</info>");
            return;
        }

        // Fallback to .php file (copy as .dist)
        $sourcePhpFile = "{$sourceDir}/{$configName}.php";
        if (file_exists($sourcePhpFile)) {
            $this->filesystem->copy($sourcePhpFile, $targetFile);
            $this->logVerbose("  <info>Distributed {$app}/{$configName}.php as .dist</info>");
            return;
        }
    }

    /**
     * Log a message if IO is available and verbose
     *
     * @param string $message Message to log
     */
    private function logVerbose(string $message): void
    {
        if ($this->io === null) {
            return;
        }

        $this->io->writeln($message);
    }
}
