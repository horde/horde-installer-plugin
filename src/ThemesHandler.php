<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use DirectoryIterator;

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
    protected $rootDir;

    /**
     * @var ThemesCatalog
     */
    public $themesCatalog;

    protected string $themesDir;

    private string $mode = 'symlink';

    public function __construct(Filesystem $filesystem, string $rootDir, string $mode = 'symlink')
    {
        $this->filesystem = $filesystem;
        $this->rootDir = $rootDir;
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
     * Cycle through applications to setup the directories for the default folder
     */
    public function setupDefaultTheme(): void
    {
        // Circle /web/ dir for apps which have a themes/default folder
        $dir = new DirectoryIterator($this->rootDir . '/web/');
        foreach ($dir as $entry) {
            $target = $entry->getPathName() . '/themes/default/';
            if (is_dir($target)) {
                $appDir = $this->themesDir . '/' . $entry->getFilename();
                $linkDir = $appDir . '/default';
                // Create themes/$app/default symlink
                $this->filesystem->ensureDirectoryExists($appDir);
                $this->mode === 'symlink' ? $this->filesystem->relativeSymlink($target, $linkDir) : copy ($target, $linkDir);
            }
        }
    }

    public function setupPackagedThemes(): void
    {
        foreach ($this->themesCatalog->toArray() as $theme) {
            foreach ($theme as $app => $appTheme) {
                $appDir = $this->themesDir . '/' . $app;
                $linkDir = $appDir . '/' . $appTheme['themeName'];
                $target = $appTheme['linkDir'];
                $this->filesystem->ensureDirectoryExists($appDir);
                $this->mode === 'symlink' ? $this->filesystem->relativeSymlink($target, $linkDir) : copy($target, $linkDir);
            }
        }
    }

    /**
     * Rebuild the link structure from index
     *
     * TODO: Unregister themes which are not really installed but indexed
     */
    public function setupThemes(): void
    {
        $this->ensureThemesFolderExists();
        $this->setupDefaultTheme();
        $this->setupPackagedThemes();
    }
}
