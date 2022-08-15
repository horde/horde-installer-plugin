<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CommandProvider implements CommandProviderCapability
{
    public function getCommands()
    {
        return [new HordeReconfigureCommand()];
    }
}
