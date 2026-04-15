<?php

declare(strict_types=1);

namespace Horde\Composer\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Composer\Util\Filesystem;
use Horde\Composer\VendorAssetLinker;

#[CoversClass(VendorAssetLinker::class)]
class VendorAssetLinkerTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/horde-vendor-asset-test-' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/vendor', 0o777, true);
        mkdir($this->tempDir . '/web', 0o777, true);
        $this->filesystem = new Filesystem();
    }

    public function tearDown(): void
    {
        $this->filesystem->removeDirectory($this->tempDir);
    }

    public function testRunLinksJsAsset(): void
    {
        $editorDir = $this->tempDir . '/vendor/horde/editor';
        mkdir($editorDir, 0o777, true);
        file_put_contents($editorDir . '/composer.json', json_encode([
            'name' => 'horde/editor',
            'extra' => [
                'horde-vendor-assets' => [
                    [
                        'package' => 'tinymce/tinymce',
                        'type' => 'js',
                        'target' => 'tinymce',
                    ],
                ],
            ],
        ]));

        $tinymceDir = $this->tempDir . '/vendor/tinymce/tinymce';
        mkdir($tinymceDir, 0o777, true);
        file_put_contents($tinymceDir . '/tinymce.min.js', '// tinymce');
        mkdir($tinymceDir . '/icons');
        file_put_contents($tinymceDir . '/icons/default.js', '// icons');

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/editor'],
            'copy',
        );
        $linker->run();

        $this->assertFileExists($this->tempDir . '/web/js/horde/tinymce/tinymce.min.js');
        $this->assertFileExists($this->tempDir . '/web/js/horde/tinymce/icons/default.js');
    }

    public function testRunLinksJsAssetWithSource(): void
    {
        $editorDir = $this->tempDir . '/vendor/horde/editor';
        mkdir($editorDir, 0o777, true);
        file_put_contents($editorDir . '/composer.json', json_encode([
            'name' => 'horde/editor',
            'extra' => [
                'horde-vendor-assets' => [
                    [
                        'package' => 'vendor/pkg',
                        'type' => 'js',
                        'source' => 'dist/js',
                        'target' => 'mypkg',
                    ],
                ],
            ],
        ]));

        $pkgDir = $this->tempDir . '/vendor/vendor/pkg/dist/js';
        mkdir($pkgDir, 0o777, true);
        file_put_contents($pkgDir . '/lib.js', '// lib');

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/editor'],
            'copy',
        );
        $linker->run();

        $this->assertFileExists($this->tempDir . '/web/js/horde/mypkg/lib.js');
    }

    public function testRunSkipsNonJsType(): void
    {
        $editorDir = $this->tempDir . '/vendor/horde/editor';
        mkdir($editorDir, 0o777, true);
        file_put_contents($editorDir . '/composer.json', json_encode([
            'name' => 'horde/editor',
            'extra' => [
                'horde-vendor-assets' => [
                    [
                        'package' => 'vendor/css-pkg',
                        'type' => 'css',
                        'target' => 'csspkg',
                    ],
                ],
            ],
        ]));

        $pkgDir = $this->tempDir . '/vendor/vendor/css-pkg';
        mkdir($pkgDir, 0o777, true);
        file_put_contents($pkgDir . '/style.css', 'body {}');

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/editor'],
            'copy',
        );
        $linker->run();

        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/js/horde/csspkg');
    }

    public function testRunSkipsPackageWithoutVendorAssets(): void
    {
        $libDir = $this->tempDir . '/vendor/horde/browser';
        mkdir($libDir, 0o777, true);
        file_put_contents($libDir . '/composer.json', json_encode([
            'name' => 'horde/browser',
        ]));

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/browser'],
            'copy',
        );
        $linker->run();

        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/js/horde');
    }

    public function testRunSkipsMissingVendorPackage(): void
    {
        $editorDir = $this->tempDir . '/vendor/horde/editor';
        mkdir($editorDir, 0o777, true);
        file_put_contents($editorDir . '/composer.json', json_encode([
            'name' => 'horde/editor',
            'extra' => [
                'horde-vendor-assets' => [
                    [
                        'package' => 'nonexistent/package',
                        'type' => 'js',
                        'target' => 'nope',
                    ],
                ],
            ],
        ]));

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/editor'],
            'copy',
        );
        $linker->run();

        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/js/horde/nope');
    }

    public function testRunHandlesMultipleAssets(): void
    {
        $editorDir = $this->tempDir . '/vendor/horde/editor';
        mkdir($editorDir, 0o777, true);
        file_put_contents($editorDir . '/composer.json', json_encode([
            'name' => 'horde/editor',
            'extra' => [
                'horde-vendor-assets' => [
                    [
                        'package' => 'tinymce/tinymce',
                        'type' => 'js',
                        'target' => 'tinymce',
                    ],
                    [
                        'package' => 'vendor/other',
                        'type' => 'js',
                        'source' => 'dist',
                        'target' => 'other',
                    ],
                ],
            ],
        ]));

        $tinymceDir = $this->tempDir . '/vendor/tinymce/tinymce';
        mkdir($tinymceDir, 0o777, true);
        file_put_contents($tinymceDir . '/tinymce.min.js', '// tinymce');

        $otherDir = $this->tempDir . '/vendor/vendor/other/dist';
        mkdir($otherDir, 0o777, true);
        file_put_contents($otherDir . '/other.js', '// other');

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/editor'],
            'copy',
        );
        $linker->run();

        $this->assertFileExists($this->tempDir . '/web/js/horde/tinymce/tinymce.min.js');
        $this->assertFileExists($this->tempDir . '/web/js/horde/other/other.js');
    }

    public function testRunSkipsPackageWithoutComposerJson(): void
    {
        $libDir = $this->tempDir . '/vendor/horde/missing';
        mkdir($libDir, 0o777, true);
        // No composer.json file

        $linker = new VendorAssetLinker(
            $this->filesystem,
            $this->tempDir . '/vendor',
            $this->tempDir . '/web',
            ['horde/missing'],
            'copy',
        );
        $linker->run();

        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/js');
    }
}
