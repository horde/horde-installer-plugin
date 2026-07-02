<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use DirectoryIterator;
use Exception;

/**
 * Themes Handler class
 *
 * This class is specifically designed to require as few as possible composer infrastructure.
 * It should also be useful for offline / register-only scenarios.
 */
class ThemesHandler
{
    /**
     * Filesystem API
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * The root package's dir
     * @var string
     */
    protected string $rootDir;
    protected string $vendorDir;

    /**
     * @var ThemesCatalog
     */
    public $themesCatalog;

    protected string $themesDir;

    private string $mode = 'proxy';

    public function __construct(
        Filesystem $filesystem,
        string $rootDir,
        string $vendorDir,
        string $mode = 'proxy'
    ) {
        $this->filesystem = $filesystem;
        $this->rootDir = $rootDir;
        $this->vendorDir = $vendorDir;
        $this->themesDir = $rootDir . '/web/themes/';
        $this->themesCatalog = new ThemesCatalog($rootDir);
        $this->mode = $mode;
    }

    /**
     * Create the basic themes folder if missing
     *
     * @return void
     */
    protected function ensureThemesFolderExists(): void
    {
        $this->filesystem->ensureDirectoryExists($this->themesDir);
    }

    /**
     * Setup themes shipped with an app
     *
     * These may be named "default" or other
     *
     */
    public function setupDefaultTheme(): void
    {
        $vendorDir = new DirectoryIterator($this->vendorDir);
        // Consider all vendors, not just "horde" - on purpose
        foreach ($vendorDir as $vendor) {
            $vendorName = $vendor->getFileName();
            if (!$vendor->isDir() || $vendor->isDot() || in_array($vendorName, ['bin', 'composer'])) {
                continue;
            }
            // Treat all packages as potential apps
            foreach (new DirectoryIterator($vendor->getPathname()) as $package) {
                $packageName = $package->getFileName();
                if (!$package->isDir() || $package->isDot()) {
                    continue;
                }
                $themesDir = $package->getPathName() . '/themes';
                if (!is_dir($themesDir)) {
                    // This package has no themes
                    continue;
                }
                $themes = new DirectoryIterator($themesDir);
                foreach ($themes as $theme) {
                    if (!$theme->isDir() || $theme->isDot()) {
                        continue;
                    }
                    $themeSourceDir = $theme->getPathname();
                    $themeName = $theme->getFileName();
                    $targetDir =  $this->themesDir . '/' . $packageName . '/' . $themeName;
                    $this->filesystem->ensureDirectoryExists(dirname($targetDir));
                    if (in_array($this->mode, ['proxy', 'symlink'])) {
                        $this->filesystem->relativeSymlink($themeSourceDir, $targetDir);
                    } else {
                        (new RecursiveCopy($themeSourceDir, $targetDir))->copy();
                    }
                }
            }
        }
    }

    public function setupPackagedThemes(): void
    {
        foreach ($this->themesCatalog->toArray() as $theme) {
            if (!is_iterable($theme)) {
                throw new Exception('ThemesCatalog is invalid');
            }
            foreach ($theme as $app => $appTheme) {
                $appDir = $this->themesDir . '/' . $app;
                $linkDir = $appDir . '/' . $appTheme['themeName'];
                $target = $appTheme['linkDir'];
                $this->filesystem->ensureDirectoryExists($appDir);
                if (in_array($this->mode, ['proxy', 'symlink'])) {
                    $this->filesystem->relativeSymlink($target, $linkDir);
                } else {
                    (new RecursiveCopy($target, $linkDir))->copy();
                }
            }
        }
    }

    /**
     * Register a theme package with the catalog.
     *
     * Thin wrapper so callers do not have to reach through the
     * public $themesCatalog property.
     */
    public function registerTheme(
        string $vendorName,
        string $packageName,
        string $installDir,
    ): void {
        $this->themesCatalog->register($vendorName, $packageName, $installDir);
    }

    /**
     * Clear the in-memory catalog before a full rebuild.
     *
     * Intended to be paired with a subsequent loop of
     * {@see registerTheme()} calls, one per currently installed
     * theme package.
     */
    public function resetCatalog(): void
    {
        $this->themesCatalog->reset();
    }

    /**
     * Rebuild the link structure from index
     *
     * Callers doing a full reconfigure should call
     * {@see resetCatalog()} beforehand and then register every currently
     * installed theme package, so stale entries are pruned.
     */
    public function setupThemes(): void
    {
        $this->ensureThemesFolderExists();
        $this->setupDefaultTheme();
        $this->setupPackagedThemes();
    }
}
