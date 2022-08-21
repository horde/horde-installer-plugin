<?php

namespace Horde\Composer\Test;

use PHPUnit\Framework\TestCase;
use Horde\Composer\ConfigLinker;

/**
 * @author     Ralf Lang <lang@b1-systems.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 */
class ConfigLinkerTest extends TestCase
{
    private ConfigLinker $linker;
    private string $fixture;
    public function setUp(): void
    {
        $this->fixture = __DIR__. '/fixture/ConfigLinker';
        $this->linker = new ConfigLinker($this->fixture);
    }

    public function testSaveAndRetrieve()
    {
        $this->linker->run();
        // Assert files are linked when target dir exists
        $this->assertFileExists($this->fixture . '/vendor/horde/horde/config/hooks.php');
        $this->assertFileDoesNotExist($this->fixture . '/vendor/horde/lunch/config/conf.php');
    }

    public function tearDown(): void
    {
        array_map('unlink', glob($this->fixture . '/vendor/horde/horde/config/*.php'));
        array_map('unlink', glob($this->fixture . '/vendor/horde/lunch/config/*.php'));
        if (is_dir($this->fixture . '/vendor/horde/horde/config/registry.d/')) {
            array_map('unlink', glob($this->fixture . '/vendor/horde/horde/config/registry.d/*'));
            rmdir($this->fixture . '/vendor/horde/horde/config/registry.d/');
        }
    }
}
