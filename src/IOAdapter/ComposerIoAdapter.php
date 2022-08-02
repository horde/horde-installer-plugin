<?php
/**
 * Common interface implemented by shims for 
 * Composer\IO\IoInterface and Symfony\Component\Console\Output\OutputInterface
 * Allows factoring out implementation into HordeReconfigureFlow
 * without losing output capabilities.
 *
 * @internal No promise of stable interface, implementation detail 
 */
declare(strict_types=1);

namespace Horde\Composer\IOAdapter;
use Composer\IO\IoInterface;

class ComposerIoAdapter implements FlowIoInterface
{
    private IoInterface $io;

    public function __construct(IoInterface $io)
    {
        $this->io = $io;
    }

    public function writeln(string $content): void
    {
        $this->io->write($content, true);
    }
}