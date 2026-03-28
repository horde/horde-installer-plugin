<?php

namespace Horde\Composer\Test;

use PHPUnit\Framework\TestCase;
use Horde\Composer\ObsoleteWebFilesCleaner;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 * @coversNothing
 */
class ObsoleteWebFilesCleanerTest extends TestCase
{
    private string $fixture;
    private string $tempDir;

    public function setUp(): void
    {
        // Use system temp directory with unique name
        $this->tempDir = sys_get_temp_dir() . '/horde-installer-test-' . uniqid();
        $this->fixture = $this->tempDir;

        // Create test directory structure
        if (!is_dir($this->fixture)) {
            mkdir($this->fixture, 0o755, true);
        }

        // Create vendor/horde/turba with some files
        $vendorDir = $this->fixture . '/vendor/horde/turba';
        mkdir($vendorDir, 0o755, true);
        file_put_contents($vendorDir . '/index.php', '<?php // Vendor index');
        file_put_contents($vendorDir . '/browse.php', '<?php // Vendor browse');

        // Create web/turba with matching and non-matching files
        $webDir = $this->fixture . '/web/turba';
        mkdir($webDir, 0o755, true);
        file_put_contents($webDir . '/index.php', '<?php // Web index');
        file_put_contents($webDir . '/browse.php', '<?php // Web browse');
        file_put_contents($webDir . '/smartmobile.php', '<?php // Obsolete file');
        file_put_contents($webDir . '/old-route.php', '<?php // Another obsolete file');
    }

    public function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->fixture)) {
            $this->recursiveRemoveDirectory($this->fixture);
        }
    }

    public function testRemoveObsoleteFiles()
    {
        $cleaner = new ObsoleteWebFilesCleaner(
            ['horde/turba'],
            $this->fixture
        );

        // Before cleanup
        $this->assertFileExists($this->fixture . '/web/turba/index.php');
        $this->assertFileExists($this->fixture . '/web/turba/browse.php');
        $this->assertFileExists($this->fixture . '/web/turba/smartmobile.php');
        $this->assertFileExists($this->fixture . '/web/turba/old-route.php');

        $cleaner->run();

        // After cleanup - files with vendor equivalents should remain
        $this->assertFileExists($this->fixture . '/web/turba/index.php');
        $this->assertFileExists($this->fixture . '/web/turba/browse.php');

        // After cleanup - obsolete files should be removed
        $this->assertFileDoesNotExist($this->fixture . '/web/turba/smartmobile.php');
        $this->assertFileDoesNotExist($this->fixture . '/web/turba/old-route.php');
    }

    public function testBrokenSymlinksRemoved()
    {
        $webFile = $this->fixture . '/web/turba/broken-link.php';
        symlink('/nonexistent/path.php', $webFile);

        $this->assertTrue(is_link($webFile));
        $this->assertFalse(file_exists($webFile));

        $cleaner = new ObsoleteWebFilesCleaner(
            ['horde/turba'],
            $this->fixture
        );

        $cleaner->run();

        // Broken symlink should be removed
        $this->assertFileDoesNotExist($webFile);
    }

    public function testNonPhpFilesIgnored()
    {
        // Create some non-PHP files
        file_put_contents($this->fixture . '/web/turba/config.xml', '<config/>');
        file_put_contents($this->fixture . '/web/turba/style.css', 'body {}');

        $cleaner = new ObsoleteWebFilesCleaner(
            ['horde/turba'],
            $this->fixture
        );

        $cleaner->run();

        // Non-PHP files should not be removed
        $this->assertFileExists($this->fixture . '/web/turba/config.xml');
        $this->assertFileExists($this->fixture . '/web/turba/style.css');
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
