<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use ErrorException;

/**
 * Links vendor assets declared in composer.json extra.horde-vendor-assets
 * to web-accessible locations.
 *
 * Horde packages can declare third-party composer package assets that need
 * to be exposed under the web root. The declarations are written by
 * horde-components from .horde.yml vendor-assets stanzas into
 * composer.json extra.horde-vendor-assets.
 *
 * Currently supports type "js" which links to web/js/horde/$target.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
class VendorAssetLinker
{
    private string $jsDir;

    /**
     * @param Filesystem $filesystem  Composer filesystem utility
     * @param string $vendorDir       Absolute path to vendor directory
     * @param string $webDir          Absolute path to web-readable root directory
     * @param string[] $packages      All horde packages (apps + libraries) to scan
     * @param string $mode            Link mode: "symlink", "proxy", or "copy"
     */
    public function __construct(
        private Filesystem $filesystem,
        private string $vendorDir,
        string $webDir,
        private array $packages,
        private string $mode = 'proxy',
    ) {
        $this->jsDir = $webDir . '/js';
    }

    /**
     * Scan packages for vendor asset declarations and link them.
     */
    public function run(): void
    {
        foreach ($this->packages as $package) {
            $composerJsonPath = $this->vendorDir . '/' . $package . '/composer.json';
            if (!is_readable($composerJsonPath)) {
                continue;
            }
            $composerData = json_decode(file_get_contents($composerJsonPath));
            if (!is_object($composerData)) {
                continue;
            }
            $assets = $composerData->extra->{'horde-vendor-assets'} ?? null;
            if (!is_array($assets)) {
                continue;
            }
            foreach ($assets as $asset) {
                $this->linkAsset($asset);
            }
        }
    }

    /**
     * Link a single vendor asset entry.
     */
    private function linkAsset(object $asset): void
    {
        $type = $asset->type ?? '';
        if ($type !== 'js') {
            // Only JS assets are supported for now
            return;
        }
        $assetPackage = $asset->package ?? '';
        $source = $asset->source ?? '';
        $target = $asset->target ?? '';
        if ($assetPackage === '' || $target === '') {
            return;
        }
        $installPath = $this->vendorDir . '/' . $assetPackage;
        if (!is_dir($installPath)) {
            return;
        }
        $sourceDir = $source !== '' ? $installPath . '/' . $source : $installPath;
        if (!is_dir($sourceDir)) {
            return;
        }
        $targetDir = $this->jsDir . '/horde/' . $target;
        $this->linkDir($sourceDir, $targetDir);
    }

    /**
     * Link all files and subdirs from source to target.
     *
     * Mirrors JsTreeLinker::linkDir() behavior.
     */
    private function linkDir(string $sourceDir, string $targetDir): void
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
            if ($sourceItem === '.' || $sourceItem === '..') {
                continue;
            }
            $sourceFile = $sourceDir . '/' . $sourceItem;
            $targetFile = $targetDir . '/' . $sourceItem;
            if (in_array($this->mode, ['symlink', 'proxy'])) {
                $this->filesystem->relativeSymlink($sourceFile, $targetFile);
            } else {
                if (is_file($sourceFile)) {
                    copy($sourceFile, $targetFile);
                }
                if (is_dir($sourceFile)) {
                    $this->linkDir($sourceFile, $targetFile);
                }
            }
        }
        closedir($sourceDirHandle);
    }
}
