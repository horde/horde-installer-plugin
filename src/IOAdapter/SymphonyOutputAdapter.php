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
use Symfony\Component\Console\Output\OutputInterface;

class SymphonyOutputAdapter implements FlowIoInterface
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function writeln(string $content): void
    {
        $this->output->writeln($content);
    }
}