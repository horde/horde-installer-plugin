<?php

declare(strict_types=1);

namespace Horde\Composer\Test;

use Composer\Util\Filesystem;
use Horde\Composer\ConfigDistributor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ConfigDistributor
 *
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 */
#[CoversClass(ConfigDistributor::class)]
class ConfigDistributorTest extends TestCase
{
    private ConfigDistributor $distributor;
    private string $fixture;
    private Filesystem $filesystem;

    public function setUp(): void
    {
        $this->fixture = __DIR__ . '/fixture/ConfigDistributor';
        $this->filesystem = new Filesystem();
        $this->distributor = new ConfigDistributor(
            $this->fixture,
            $this->fixture . '/vendor',
            $this->filesystem,
        );
    }

    public function testDistributesDistVariantWhenAvailable(): void
    {
        // backends.php.dist exists in vendor
        $this->distributor->run(['horde/testapp']);

        $targetFile = $this->fixture . '/var/config/testapp/backends.php.dist';
        $this->assertFileExists($targetFile);

        // Should contain v2 content (from vendor)
        $content = file_get_contents($targetFile);
        $this->assertStringContainsString("'v2'", $content);
    }

    public function testFallsBackToPhpFileWhenNoDistVariant(): void
    {
        // prefs.php exists but no prefs.php.dist in vendor
        $this->distributor->run(['horde/testapp']);

        $targetFile = $this->fixture . '/var/config/testapp/prefs.php.dist';
        $this->assertFileExists($targetFile);

        // Should contain prefs.php content
        $content = file_get_contents($targetFile);
        $this->assertStringContainsString('$prefs', $content);
    }

    public function testAlwaysOverwritesExistingDistFiles(): void
    {
        // var/config/testapp/backends.php.dist exists with v1
        $targetFile = $this->fixture . '/var/config/testapp/backends.php.dist';
        $oldContent = file_get_contents($targetFile);
        $this->assertStringContainsString("'v1'", $oldContent);

        // Run distributor
        $this->distributor->run(['horde/testapp']);

        // Should now contain v2 (overwritten)
        $newContent = file_get_contents($targetFile);
        $this->assertStringContainsString("'v2'", $newContent);
        $this->assertStringNotContainsString("'v1'", $newContent);
    }

    public function testHandlesMultipleConfigFiles(): void
    {
        $this->distributor->run(['horde/testapp']);

        // backends.php.dist should exist
        $this->assertFileExists($this->fixture . '/var/config/testapp/backends.php.dist');

        // prefs.php.dist should exist (from prefs.php)
        $this->assertFileExists($this->fixture . '/var/config/testapp/prefs.php.dist');

        // conf.php.dist should exist
        $this->assertFileExists($this->fixture . '/var/config/testapp/conf.php.dist');
    }

    public function testCreatesVarConfigDirIfMissing(): void
    {
        $newAppDir = $this->fixture . '/var/config/newapp';

        // Remove if exists
        if (is_dir($newAppDir)) {
            $this->filesystem->removeDirectory($newAppDir);
        }

        $this->assertDirectoryDoesNotExist($newAppDir);

        // Create vendor config for newapp
        $vendorNewAppConfig = $this->fixture . '/vendor/horde/newapp/config';
        $this->filesystem->ensureDirectoryExists($vendorNewAppConfig);
        file_put_contents($vendorNewAppConfig . '/conf.php.dist', '<?php $conf = [];');

        // Run distributor
        $this->distributor->run(['horde/newapp']);

        // Should create directory and file
        $this->assertDirectoryExists($newAppDir);
        $this->assertFileExists($newAppDir . '/conf.php.dist');

        // Cleanup
        $this->filesystem->removeDirectory($newAppDir);
        $this->filesystem->removeDirectory($this->fixture . '/vendor/horde/newapp');
    }

    public function testSkipsAppsWithoutConfigFiles(): void
    {
        // App with no config directory
        $noConfigApp = $this->fixture . '/vendor/horde/noconfigapp';
        $varConfigNoConfig = $this->fixture . '/var/config/noconfigapp';

        // Clean up if exists from previous run
        if (is_dir($varConfigNoConfig)) {
            $this->filesystem->removeDirectory($varConfigNoConfig);
        }

        $this->filesystem->ensureDirectoryExists($noConfigApp);

        // Should not throw exception
        $this->distributor->run(['horde/noconfigapp']);

        // Should not create var/config dir for this app
        $this->assertDirectoryDoesNotExist($varConfigNoConfig);

        // Cleanup
        $this->filesystem->removeDirectory($noConfigApp);
    }

    public function testDistributesRegistryPhpDist(): void
    {
        // Verify that registry.php.dist is distributed from vendor to var/config
        // This is part of the registry file handling user story

        // Create vendor registry.php.dist
        $vendorHordeConfig = $this->fixture . '/vendor/horde/horde/config';
        $this->filesystem->ensureDirectoryExists($vendorHordeConfig);
        file_put_contents($vendorHordeConfig . '/registry.php.dist', "<?php\n// Registry template\n\$registry = [];\n");

        // Run distributor
        $this->distributor->run(['horde/horde']);

        // Verify registry.php.dist is copied to var/config
        $varRegistryDist = $this->fixture . '/var/config/horde/registry.php.dist';
        $this->assertFileExists($varRegistryDist);

        // Verify content
        $content = file_get_contents($varRegistryDist);
        $this->assertStringContainsString('Registry template', $content);

        // Cleanup
        $this->filesystem->removeDirectory($this->fixture . '/vendor/horde/horde');
        if (file_exists($varRegistryDist)) {
            unlink($varRegistryDist);
        }
        if (is_dir($this->fixture . '/var/config/horde')) {
            // Only remove if we created it
            if (!file_exists($this->fixture . '/var/config/horde/hooks.php')) {
                $this->filesystem->removeDirectory($this->fixture . '/var/config/horde');
            }
        }
    }

    public function testFallsBackToRegistryPhpWhenNoDistVariant(): void
    {
        // User story: if no registry.php.dist exists in vendor,
        // var/config/horde/registry.php.dist is created from vendor/horde/horde/config/registry.php

        // Create vendor registry.php (NO .dist variant)
        $vendorHordeConfig = $this->fixture . '/vendor/horde/horde/config';
        $this->filesystem->ensureDirectoryExists($vendorHordeConfig);
        file_put_contents($vendorHordeConfig . '/registry.php', "<?php\n// Registry from registry.php\n\$registry = [];\n");

        // Run distributor
        $this->distributor->run(['horde/horde']);

        // Verify registry.php.dist is created (from registry.php)
        $varRegistryDist = $this->fixture . '/var/config/horde/registry.php.dist';
        $this->assertFileExists($varRegistryDist);

        // Verify content came from registry.php
        $content = file_get_contents($varRegistryDist);
        $this->assertStringContainsString('Registry from registry.php', $content);

        // Cleanup
        $this->filesystem->removeDirectory($this->fixture . '/vendor/horde/horde');
        if (file_exists($varRegistryDist)) {
            unlink($varRegistryDist);
        }
        if (is_dir($this->fixture . '/var/config/horde')) {
            if (!file_exists($this->fixture . '/var/config/horde/hooks.php')) {
                $this->filesystem->removeDirectory($this->fixture . '/var/config/horde');
            }
        }
    }

    public function tearDown(): void
    {
        // Clean up created .dist files
        $varConfig = $this->fixture . '/var/config/testapp';
        if (is_dir($varConfig)) {
            foreach (glob($varConfig . '/*.dist') as $file) {
                if (basename($file) !== 'backends.php.dist') {
                    // Keep backends.php.dist for next test
                    unlink($file);
                }
            }
        }

        // Restore original backends.php.dist (v1)
        $backendsFile = $varConfig . '/backends.php.dist';
        file_put_contents($backendsFile, "<?php\n// Test fixture: old backends.php.dist version 1\n\$backends = ['version' => 'v1'];\n");
    }
}
