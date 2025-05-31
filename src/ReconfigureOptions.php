<?php
declare(strict_types=1);
namespace Horde\Composer;
use InvalidArgumentException;

class ReconfigureOptions
{
    public function __construct(public readonly string $mode = self::MODE_SYMLINK, public readonly bool $force = false)
    {
        if (!in_array($mode, ['symlink', 'proxy', 'copy'])) {
            throw new InvalidArgumentException('Invalid mode. Must be "symlink", "proxy" or "copy". "symlink" is the current default.');
        }
    }
}