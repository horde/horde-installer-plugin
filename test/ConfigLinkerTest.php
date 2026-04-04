<?php

declare(strict_types=1);

namespace Horde\Composer\Test;

use PHPUnit\Framework\TestCase;
use Horde\Composer\ConfigLinker;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 * @coversNothing
 */
class ConfigLinkerTest extends TestCase
{
    private ConfigLinker $linker;
    private string $fixture;
    private string $ephemeralDir;

    public function setUp(): void
    {
        // Use ephemeral copy of fixture to avoid polluting source fixtures
        $originalFixture = __DIR__ . '/fixture/ConfigLinker';
        $this->ephemeralDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        $this->recursiveCopy($originalFixture, $this->ephemeralDir);
        $this->fixture = $this->ephemeralDir;
        $this->linker = new ConfigLinker($this->fixture);
    }

    public function testSaveAndRetrieve()
    {
        $this->linker->run();
        // Assert files are linked when target dir exists
        $this->assertFileExists($this->fixture . '/vendor/horde/horde/config/hooks.php');
        // lunch/config now exists (for .dist filter test), so conf.php should be linked
        $this->assertFileExists($this->fixture . '/vendor/horde/lunch/config/conf.php');
        $this->assertTrue(is_link($this->fixture . '/vendor/horde/lunch/config/conf.php'));
    }

    public function testBrokenSymlinkDetection()
    {
        // First run creates symlinks
        $this->linker->run();
        $targetFile = $this->fixture . '/vendor/horde/horde/config/hooks.php';
        $this->assertFileExists($targetFile);

        // Create broken symlink by unlinking and recreating with bad target
        unlink($targetFile);
        symlink('/nonexistent/path/hooks.php', $targetFile);

        // Verify it's a broken symlink
        $this->assertTrue(is_link($targetFile));
        $this->assertFalse(file_exists($targetFile));

        // Second run should remove broken symlink and recreate
        $this->linker->run();

        // Now it should be a valid symlink
        $this->assertTrue(is_link($targetFile));
        $this->assertTrue(file_exists($targetFile));
    }

    public function testDoesNotLinkDistFilesBackToVendor()
    {
        // Create a .dist file in var/config that should NOT be symlinked
        $distFile = $this->fixture . '/var/config/lunch/backends.php.dist';
        file_put_contents($distFile, "<?php\n// Test fixture: .dist file that should NOT be symlinked back\n\$backends_dist = ['dist' => 'template'];\n");
        $this->assertFileExists($distFile);

        // Run linker
        $this->linker->run();

        // .dist file should NOT be symlinked to vendor
        $vendorDistFile = $this->fixture . '/vendor/horde/lunch/config/backends.php.dist';
        $this->assertFileDoesNotExist($vendorDistFile);

        // But .local.php files SHOULD be symlinked
        $vendorLocalFile = $this->fixture . '/vendor/horde/lunch/config/backend.local.php';
        $this->assertFileExists($vendorLocalFile);
        $this->assertTrue(is_link($vendorLocalFile));
    }

    public function testUserConfPhpIsLinkedToVendor()
    {
        // User story: user creates conf.php in var/config/horde/
        // horde:reconfigure should place it in vendor/horde/horde/config/conf.php
        $userConfFile = $this->fixture . '/var/config/horde/conf.php';
        file_put_contents($userConfFile, "<?php\n// User's configuration\n\$conf['test'] = 'value';\n");
        $this->assertFileExists($userConfFile);

        // Run linker
        $this->linker->run();

        // conf.php should be symlinked to vendor
        $vendorConfFile = $this->fixture . '/vendor/horde/horde/config/conf.php';
        $this->assertFileExists($vendorConfFile);
        $this->assertTrue(is_link($vendorConfFile));

        // Verify it points to the user's conf.php
        $this->assertEquals($userConfFile, readlink($vendorConfFile));

        // Verify content is accessible
        $content = file_get_contents($vendorConfFile);
        $this->assertStringContainsString("User's configuration", $content);
    }

    public function testRemovedUserFileIsDeletedFromVendor()
    {
        // User story: user previously had prefs-localhost.php in var/config/lunch/
        // Now they remove it from var/config and run horde:reconfigure
        // The symlink in vendor should be removed automatically

        // Step 1: Create user file and link it
        $userPrefsFile = $this->fixture . '/var/config/lunch/prefs-localhost.php';
        file_put_contents($userPrefsFile, "<?php\n// User's prefs\n\$prefs = [];\n");

        $this->linker->run();

        $vendorPrefsFile = $this->fixture . '/vendor/horde/lunch/config/prefs-localhost.php';
        $this->assertFileExists($vendorPrefsFile);
        $this->assertTrue(is_link($vendorPrefsFile));

        // Step 2: User removes the file from var/config
        unlink($userPrefsFile);
        $this->assertFileDoesNotExist($userPrefsFile);

        // Symlink becomes broken
        $this->assertTrue(is_link($vendorPrefsFile));
        $this->assertFalse(file_exists($vendorPrefsFile)); // broken symlink

        // Step 3: Run linker again - should remove orphaned symlink
        $this->linker->run();

        // Orphaned symlink should be removed
        $this->assertFalse(is_link($vendorPrefsFile));
        $this->assertFileDoesNotExist($vendorPrefsFile);
    }

    public function testRemovedUserFileIsDeletedFromVendorInCopyMode()
    {
        // Same as testRemovedUserFileIsDeletedFromVendor but in copy mode
        // Orphaned copies should also be removed

        // Create a linker in copy mode
        $copyLinker = new ConfigLinker($this->fixture, 'copy');

        // Step 1: Create user file and copy it
        $userPrefsFile = $this->fixture . '/var/config/lunch/prefs-copy-test.php';
        file_put_contents($userPrefsFile, "<?php\n// User's prefs in copy mode\n\$prefs = [];\n");

        $copyLinker->run();

        $vendorPrefsFile = $this->fixture . '/vendor/horde/lunch/config/prefs-copy-test.php';
        $this->assertFileExists($vendorPrefsFile);
        $this->assertFalse(is_link($vendorPrefsFile)); // regular file, not symlink

        // Step 2: User removes the file from var/config
        unlink($userPrefsFile);
        $this->assertFileDoesNotExist($userPrefsFile);

        // Step 3: Run linker again - should remove orphaned copy
        $copyLinker->run();

        // Orphaned copy should be removed
        $this->assertFileDoesNotExist($vendorPrefsFile);
    }

    public function testRegistryFileHandling()
    {
        // User story: Registry file handling follows specific rules

        // Setup test files
        $varConfigHorde = $this->fixture . '/var/config/horde';
        $vendorConfigHorde = $this->fixture . '/vendor/horde/horde/config';

        // 1. registry.php in var/config does nothing (should not be linked)
        $registryPhp = $varConfigHorde . '/registry.php';
        file_put_contents($registryPhp, "<?php\n// User's registry.php - should not be linked\n");

        // 2. registry.local.php should be linked
        file_put_contents($varConfigHorde . '/registry.local.php', "<?php\n// Local registry\n");

        // 3. registry-domain.php should be linked
        $registryDomain = $varConfigHorde . '/registry-example.com.php';
        file_put_contents($registryDomain, "<?php\n// Domain registry\n");

        // 4. registry.d/*.php should be linked
        file_put_contents($varConfigHorde . '/registry.d/app-lunch.php', "<?php\n// App lunch\n");
        file_put_contents($varConfigHorde . '/registry.d/app-test.php', "<?php\n// App test\n");

        // Run linker
        $this->linker->run();

        // Verify: registry.php is NOT linked
        $this->assertFileDoesNotExist($vendorConfigHorde . '/registry.php');

        // Verify: registry.local.php IS linked
        $this->assertFileExists($vendorConfigHorde . '/registry.local.php');
        $this->assertTrue(is_link($vendorConfigHorde . '/registry.local.php'));

        // Verify: registry-domain.php IS linked
        $this->assertFileExists($vendorConfigHorde . '/registry-example.com.php');
        $this->assertTrue(is_link($vendorConfigHorde . '/registry-example.com.php'));

        // Verify: registry.d/*.php files ARE linked
        $this->assertFileExists($vendorConfigHorde . '/registry.d/app-lunch.php');
        $this->assertTrue(is_link($vendorConfigHorde . '/registry.d/app-lunch.php'));
        $this->assertFileExists($vendorConfigHorde . '/registry.d/app-test.php');
        $this->assertTrue(is_link($vendorConfigHorde . '/registry.d/app-test.php'));

        // Cleanup test files for next part
        unlink($registryPhp);
        unlink($registryDomain);
        unlink($varConfigHorde . '/registry.d/app-test.php');
    }

    public function testOrphanedRegistryDFilesAreRemoved()
    {
        // User story: orphaned files in vendor/horde/horde/config/registry.d/
        // with no equivalent in var/config/horde/registry.d/ get removed

        $varConfigHorde = $this->fixture . '/var/config/horde';
        $vendorConfigHorde = $this->fixture . '/vendor/horde/horde/config';

        // Step 1: Create and link a registry.d file
        $appFile = $varConfigHorde . '/registry.d/app-orphan-test.php';
        file_put_contents($appFile, "<?php\n// App orphan test\n");

        $this->linker->run();

        $vendorAppFile = $vendorConfigHorde . '/registry.d/app-orphan-test.php';
        $this->assertFileExists($vendorAppFile);
        $this->assertTrue(is_link($vendorAppFile));

        // Step 2: Remove from var/config (user deletes it)
        unlink($appFile);
        $this->assertFileDoesNotExist($appFile);

        // Step 3: Run linker - should remove orphaned symlink
        $this->linker->run();

        // Verify: orphaned symlink is removed
        $this->assertFileDoesNotExist($vendorAppFile);
    }

    public function testRegistryPhpIsNotLinkedEvenIfItExists()
    {
        // Specific test: registry.php in var/config does nothing
        // Even if user creates it, it should never be linked to vendor

        $varConfigHorde = $this->fixture . '/var/config/horde';
        $vendorConfigHorde = $this->fixture . '/vendor/horde/horde/config';

        // Create registry.php in var/config
        $registryPhp = $varConfigHorde . '/registry.php';
        file_put_contents($registryPhp, "<?php\n// This should not be linked\n\$registry = 'test';\n");

        $this->linker->run();

        // Verify it's NOT in vendor
        $vendorRegistryPhp = $vendorConfigHorde . '/registry.php';
        $this->assertFileDoesNotExist($vendorRegistryPhp);

        // Cleanup
        unlink($registryPhp);
    }

    public function tearDown(): void
    {
        // Clean up ephemeral fixture
        if (is_dir($this->ephemeralDir)) {
            $this->recursiveRemove($this->ephemeralDir);
        }
    }

    private function recursiveCopy(string $source, string $dest): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0o755, true);
        }

        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $item;
            $destPath = $dest . '/' . $item;

            if (is_link($sourcePath)) {
                // Don't copy symlinks
                continue;
            } elseif (is_dir($sourcePath)) {
                $this->recursiveCopy($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->recursiveRemove($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
