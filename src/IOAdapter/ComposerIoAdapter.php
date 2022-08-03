<?php
/**
 * Common interface implemented by shims for
 * Composer\IO\IOInterface and Symfony\Component\Console\Output\OutputInterface
 * Allows factoring out implementation into HordeReconfigureFlow
 * without losing output capabilities.
 *
 * @internal No promise of stable interface, implementation detail
 */
declare(strict_types=1);

namespace Horde\Composer\IOAdapter;

use Composer\IO\IOInterface;

class ComposerIoAdapter implements FlowIoInterface
{
    private IOInterface $io;

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function writeln(string $content): void
    {
        $this->io->write($content, true);
    }
}
