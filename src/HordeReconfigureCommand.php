<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Command\BaseCommand;
use Composer\Factory as ComposerFactory;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Horde\Composer\IOAdapter\SymphonyOutputAdapter;

class HordeReconfigureCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('horde:reconfigure')->setAliases(['horde-reconfigure']);
        $this->setDescription('Rewrite autogenerated configuration');
        $this->setHelp(
            <<<EOT
                Horde Installer writes various symlinks and files with installation specific paths.
                If you move your installation around or manually tinker with local files, you may need to re-run this.
                EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (method_exists($this, 'requireComposer')) {
            $composer = $this->requireComposer();
        } else {
            $composer = $this->getComposer();
        }
        if (!$composer) {
            die('Error: Command was run without a relation to composer itself');
        }
        // This is needed to support PHP 7.4 (no union types) for both Composer 2.2 / 2.3
        // Cannot use instanceof here as the class will not exist in 2.2.
        if (get_class($composer) === 'Composer\PartialComposer') {
            $flow = HordeReconfigureFlow::fromPartialComposer($composer, new SymphonyOutputAdapter($output));
        } else {
            $flow = HordeReconfigureFlow::fromComposer($composer, new SymphonyOutputAdapter($output));
        }
        return $flow->run();
    }
}
