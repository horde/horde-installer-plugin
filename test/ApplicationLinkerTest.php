<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (MIT). If you
 * did not receive this file, see https://opensource.org/licenses/MIT.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license https://opensource.org/licenses/MIT MIT
 * @package HordeInstallerPlugin
 */

namespace Horde\Composer\Test;

use Composer\Util\Filesystem;
use Horde\Composer\ApplicationLinker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplicationLinker::class)]
class ApplicationLinkerTest extends TestCase
{
    private string $tempDir = '';
    private ?Filesystem $filesystem = null;

    public function setUp(): void
    {
        if (!class_exists(Filesystem::class)) {
            foreach ([
                dirname(__DIR__) . '/vendor/autoload.php',
                '/usr/share/php/Composer/autoload.php',
            ] as $autoload) {
                if (is_file($autoload)) {
                    require_once $autoload;
                }
                if (class_exists(Filesystem::class)) {
                    break;
                }
            }
        }
        if (!class_exists(Filesystem::class)) {
            $this->markTestSkipped('composer/composer is required to run ApplicationLinker tests');
        }

        $this->tempDir = sys_get_temp_dir() . '/horde-app-linker-test-' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/vendor', 0o777, true);
        mkdir($this->tempDir . '/web', 0o777, true);
        $this->filesystem = new Filesystem();
    }

    public function tearDown(): void
    {
        if ($this->filesystem instanceof Filesystem && $this->tempDir !== '') {
            $this->filesystem->removeDirectory($this->tempDir);
        }
    }

    /**
     * Accidental sandbox layout (web/, var/) in an app package must not be
     * copied into web/$app, and dangling JS symlinks under web/ must not abort
     * proxy-mode linking of the real entry points.
     */
    public function testProxyModeSkipsSandboxWebDirAndDanglingSymlinks(): void
    {
        $appDir = $this->tempDir . '/vendor/horde/gollem';
        mkdir($appDir, 0o777, true);
        file_put_contents($appDir . '/index.php', "<?php\nrequire __DIR__ . '/manager.php';\n");
        file_put_contents($appDir . '/manager.php', "<?php\necho 'manager';\n");

        $sandboxJs = $appDir . '/web/js/horde';
        mkdir($sandboxJs, 0o777, true);
        symlink(
            '../../../vendor/horde/core/js/accesskeys.js',
            $sandboxJs . '/accesskeys.js',
        );
        mkdir($appDir . '/var/config', 0o777, true);
        file_put_contents($appDir . '/var/config/horde.local.php', "<?php\n");

        $linker = new ApplicationLinker(
            $this->filesystem,
            ['horde/gollem'],
            $this->tempDir,
            'proxy',
        );
        $linker->run();

        $this->assertFileExists($this->tempDir . '/web/gollem/index.php');
        $this->assertFileExists($this->tempDir . '/web/gollem/manager.php');
        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/gollem/web');
        $this->assertDirectoryDoesNotExist($this->tempDir . '/web/gollem/var');
        $this->assertFileDoesNotExist($this->tempDir . '/web/gollem/web/js/horde/accesskeys.js');
    }
}
