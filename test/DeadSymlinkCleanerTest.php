<?php

namespace Horde\Composer\Test;

use Horde\Composer\DeadSymlinkCleaner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 */
#[CoversClass(DeadSymlinkCleaner::class)]
class DeadSymlinkCleanerTest extends TestCase
{
    private string $fixture;
    private string $tempDir;

    public function setUp(): void
    {
        // Use system temp directory with unique name
        $this->tempDir = sys_get_temp_dir() . '/horde-symlink-test-' . uniqid();
        $this->fixture = $this->tempDir;

        // Create test directory structure
        if (!is_dir($this->fixture)) {
            mkdir($this->fixture, 0o755, true);
        }

        // Create web/js and web/themes directories
        $jsDir = $this->fixture . '/web/js';
        $themesDir = $this->fixture . '/web/themes';
        mkdir($jsDir, 0o755, true);
        mkdir($themesDir, 0o755, true);

        // Create some real target files/directories
        mkdir($this->fixture . '/vendor/horde/turba/js', 0o755, true);
        file_put_contents($this->fixture . '/vendor/horde/turba/js/app.js', '// app.js');

        mkdir($this->fixture . '/vendor/horde/horde/themes/default', 0o755, true);
        file_put_contents($this->fixture . '/vendor/horde/horde/themes/default/screen.css', '/* css */');
    }

    public function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->fixture)) {
            $this->recursiveRemoveDirectory($this->fixture);
        }
    }

    /**
     * Test removal of dead symlinks
     */
    public function testRemoveDeadSymlinks()
    {
        $jsDir = $this->fixture . '/web/js';
        $themesDir = $this->fixture . '/web/themes';

        // Create a working symlink in web/js/
        symlink(
            $this->fixture . '/vendor/horde/turba/js',
            $jsDir . '/turba'
        );

        // Create a dead symlink in web/js/ (points to non-existent location)
        symlink(
            $this->fixture . '/vendor/horde/kronolith/js',
            $jsDir . '/kronolith'
        );

        // Create a working symlink in web/themes/
        symlink(
            $this->fixture . '/vendor/horde/horde/themes/default',
            $themesDir . '/default'
        );

        // Create a dead symlink in web/themes/
        symlink(
            $this->fixture . '/vendor/horde/horde/themes/silver',
            $themesDir . '/silver'
        );

        // Verify initial state
        $this->assertTrue(is_link($jsDir . '/turba'));
        $this->assertTrue(is_link($jsDir . '/kronolith'));
        $this->assertTrue(is_link($themesDir . '/default'));
        $this->assertTrue(is_link($themesDir . '/silver'));

        // Run cleaner
        $cleaner = new DeadSymlinkCleaner($this->fixture);
        $removed = $cleaner->run();

        // Should have removed 2 dead symlinks
        $this->assertEquals(2, $removed);

        // Working symlinks should still exist
        $this->assertTrue(is_link($jsDir . '/turba'));
        $this->assertTrue(file_exists($jsDir . '/turba'));
        $this->assertTrue(is_link($themesDir . '/default'));
        $this->assertTrue(file_exists($themesDir . '/default'));

        // Dead symlinks should be removed
        $this->assertFalse(file_exists($jsDir . '/kronolith'));
        $this->assertFalse(file_exists($themesDir . '/silver'));
    }

    /**
     * Test nested dead symlinks
     */
    public function testNestedDeadSymlinks()
    {
        // Create nested directory structure
        $nestedJs = $this->fixture . '/web/js/horde/libs';
        mkdir($nestedJs, 0o755, true);

        // Create a dead symlink in nested directory
        symlink(
            $this->fixture . '/vendor/nonexistent/lib.js',
            $nestedJs . '/removed-lib.js'
        );

        $cleaner = new DeadSymlinkCleaner($this->fixture);
        $removed = $cleaner->run();

        // Should have removed 1 dead symlink
        $this->assertEquals(1, $removed);

        // Dead symlink should be removed
        $this->assertFalse(file_exists($nestedJs . '/removed-lib.js'));
    }

    /**
     * Test handling when no dead symlinks exist
     */
    public function testNoDeadSymlinks()
    {
        $jsDir = $this->fixture . '/web/js';

        // Create only working symlinks
        symlink(
            $this->fixture . '/vendor/horde/turba/js',
            $jsDir . '/turba'
        );

        $cleaner = new DeadSymlinkCleaner($this->fixture);
        $removed = $cleaner->run();

        // Should not have removed anything
        $this->assertEquals(0, $removed);

        // Symlink should still exist
        $this->assertTrue(is_link($jsDir . '/turba'));
        $this->assertTrue(file_exists($jsDir . '/turba'));
    }

    /**
     * Test handling when web directory is missing
     */
    public function testMissingWebDirectory()
    {
        // Create a fixture without web/ directory
        $noWebFixture = sys_get_temp_dir() . '/horde-noweb-test-' . uniqid();
        mkdir($noWebFixture, 0o755, true);

        $cleaner = new DeadSymlinkCleaner($noWebFixture);
        $removed = $cleaner->run();

        // Should handle gracefully
        $this->assertEquals(0, $removed);

        // Clean up
        rmdir($noWebFixture);
    }

    /**
     * Test custom scan directories
     */
    public function testCustomScanDirs()
    {
        $cleaner = new DeadSymlinkCleaner($this->fixture);

        // Default should be js and themes
        $this->assertEquals(['js', 'themes'], $cleaner->getScanDirs());

        // Set custom directories
        $cleaner->setScanDirs(['js', 'themes', 'static']);
        $this->assertEquals(['js', 'themes', 'static'], $cleaner->getScanDirs());
    }

    /**
     * Test that only symlinks are affected
     */
    public function testOnlySymlinksAffected()
    {
        $jsDir = $this->fixture . '/web/js';

        // Create a regular file
        file_put_contents($jsDir . '/regular-file.js', '// regular');

        // Create a regular directory
        mkdir($jsDir . '/regular-dir');

        // Create a dead symlink
        symlink(
            $this->fixture . '/nonexistent',
            $jsDir . '/dead-link'
        );

        $cleaner = new DeadSymlinkCleaner($this->fixture);
        $removed = $cleaner->run();

        // Should only remove the dead symlink
        $this->assertEquals(1, $removed);

        // Regular file and directory should remain
        $this->assertFileExists($jsDir . '/regular-file.js');
        $this->assertDirectoryExists($jsDir . '/regular-dir');

        // Dead symlink should be removed
        $this->assertFalse(file_exists($jsDir . '/dead-link'));
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir Directory to remove
     */
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
                // Remove symlinks without following them
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
