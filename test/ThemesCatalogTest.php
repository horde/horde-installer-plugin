<?php

declare(strict_types=1);

namespace Horde\Composer\Test;

use Horde\Composer\ThemesCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 */
#[CoversClass(ThemesCatalog::class)]
class ThemesCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/horde-themes-catalog-test-' . uniqid();
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $this->recursiveRemoveDirectory($this->root);
        }
    }

    /**
     * register() should add every app subdirectory under the install
     * dir to the catalog, keyed by the theme name.
     */
    public function testRegisterAddsEveryAppSubdir(): void
    {
        $installDir = $this->makeThemePackage('theme-silver', ['horde', 'turba']);

        $catalog = new ThemesCatalog($this->root);
        $catalog->register('horde', 'theme-silver', $installDir);

        $data = $catalog->toArray();
        $this->assertArrayHasKey('silver', $data);
        $this->assertArrayHasKey('horde', $data['silver']);
        $this->assertArrayHasKey('turba', $data['silver']);
        $this->assertSame($installDir, $data['silver']['horde']['provider']);
        $this->assertSame('theme-silver', $data['silver']['horde']['packageName']);
    }

    /**
     * A fresh ThemesCatalog reads themes.json from disk. reset()
     * clears the loaded state so a subsequent register() rebuilds
     * from scratch instead of accreting stale entries.
     *
     * This is the exact scenario the reconfigure flow triggers when
     * a theme package has been uninstalled: the on-disk row for that
     * package must not survive the next reconfigure run.
     */
    public function testResetDropsStaleEntriesBeforeRebuild(): void
    {
        // Simulate a previous run that registered two theme packages.
        $stale = $this->makeThemePackage('theme-obsolete', ['horde']);
        $keep = $this->makeThemePackage('theme-silver', ['horde', 'turba']);

        $first = new ThemesCatalog($this->root);
        $first->register('horde', 'theme-obsolete', $stale);
        $first->register('horde', 'theme-silver', $keep);

        // themes.json now contains both theme names.
        $onDisk = json_decode(
            (string) file_get_contents($this->root . '/themes.json'),
            true,
        );
        $this->assertArrayHasKey('obsolete', $onDisk);
        $this->assertArrayHasKey('silver', $onDisk);

        // A new invocation loads the catalog from disk, then the
        // reconfigure flow calls reset() and re-registers only the
        // still-installed packages.
        $second = new ThemesCatalog($this->root);
        $second->reset();
        $second->register('horde', 'theme-silver', $keep);

        $result = $second->toArray();
        $this->assertArrayNotHasKey('obsolete', $result);
        $this->assertArrayHasKey('silver', $result);

        // themes.json on disk must reflect the same pruning: the
        // final register() call also saves.
        $finalOnDisk = json_decode(
            (string) file_get_contents($this->root . '/themes.json'),
            true,
        );
        $this->assertArrayNotHasKey('obsolete', $finalOnDisk);
        $this->assertArrayHasKey('silver', $finalOnDisk);
    }

    /**
     * reset() on a freshly constructed catalog with no prior state
     * is a benign no-op.
     */
    public function testResetOnEmptyCatalogIsHarmless(): void
    {
        $catalog = new ThemesCatalog($this->root);
        $catalog->reset();
        $this->assertSame([], $catalog->toArray());
    }

    /**
     * Build a fake theme package install directory containing one
     * subdirectory per app the package themes.
     *
     * @param list<string> $apps
     * @return string  Absolute path to the install dir.
     */
    private function makeThemePackage(string $packageName, array $apps): string
    {
        $dir = $this->root . '/vendor/horde/' . $packageName;
        mkdir($dir, 0o755, true);
        foreach ($apps as $app) {
            mkdir($dir . '/' . $app, 0o755, true);
            // Drop a marker file so the subdir is not empty; not
            // strictly required by register() but matches reality.
            file_put_contents($dir . '/' . $app . '/screen.css', '/* ' . $app . ' */');
        }
        return $dir;
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
