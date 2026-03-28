<?php

namespace Horde\Composer\Test;

use PHPUnit\Framework\TestCase;
use Horde\Composer\RecursiveCopy;

/**
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @license    http://www.horde.org/licenses/lgpl LGPL
 * @category   Horde
 * @package    HordeInstallerPlugin
 * @subpackage UnitTests
 * @coversNothing
 */
class RecursiveCopyTest extends TestCase
{
    private RecursiveCopy $copy;
    private string $fixture;
    public function setUp(): void
    {
        $this->fixture = __DIR__ . '/fixture/RecursiveCopy';
        $this->copy = new RecursiveCopy($this->fixture . '/source', $this->fixture . '/dest');
    }

    public function testCopyTree()
    {
        $this->copy->copy();
        // Assert files are copied when target dir exists
        $this->assertFileExists($this->fixture . '/dest');
        $this->assertFileExists($this->fixture . '/dest/somejson.json');
        $this->assertFileExists($this->fixture . '/dest/sub1');
        $this->assertFileExists($this->fixture . '/dest/sub1/sub1file.txt');
        $this->assertFileExists($this->fixture . '/dest/sub1/sub2');
        $this->assertFileExists($this->fixture . '/dest/sub1/sub2/egal.txt');
    }

    public function tearDown(): void
    {
        // Clean up copied files
        if (is_dir($this->fixture . '/dest')) {
            $this->recursiveRemoveDirectory($this->fixture . '/dest');
        }
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
