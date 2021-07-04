<?php

namespace Horde\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class HordeInstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new HordeInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        $installer = new HordeInstaller($io, $composer);
        $composer->getInstallationManager()->removeInstaller($installer);
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        return;
    }
}
