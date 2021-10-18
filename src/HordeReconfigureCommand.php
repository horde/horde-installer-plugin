<?php
declare(strict_types = 1);
namespace Horde\Composer;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Command\BaseCommand;
use Composer\InstalledVersions;

class HordeReconfigureCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('horde-reconfigure');
        $this->setDescription('Rewrite autogenerated configuration');
        $this->setHelp(
                <<<EOT
Horde Installer writes various symlinks and files with installation specific paths.
If you move your installation around or manually tinker with local files, you may need to re-run this.
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get installed packages of types handled by installer
        $hordeApps = InstalledVersions::getInstalledPackagesByType('horde-application');
        $hordeLibraries = InstalledVersions::getInstalledPackagesByType('horde-library');
        $hordeThemes = InstalledVersions::getInstalledPackagesByType('horde-theme');
        $composer = $this->getComposer();
        $rootPackage = $composer->getPackage();
        $rootPackageDir = dirname($composer->getConfig()->get('vendor-dir'));
        $output->writeln('Applying /presets for absent files in /var/config');
        $output->writeln('Looking for registry snippets from apps');
        $output->writeln('Writing app configs to /var/config dir');
        $output->writeln('Linking app configs to /web Dir');
        $output->writeln('Linking javascript tree to /web/js');
        $output->writeln('Linking themes tree to /web/themes');
    }
}