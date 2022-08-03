<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Plugin\Capable;

class HordeInstallerPlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    protected HordeInstaller $installer;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->installer = new HordeInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $composer->getInstallationManager()->removeInstaller($this->installer);
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        return;
    }

    /**
     * Expose which events are handled by which handler
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-autoload-dump' => array('reconfigureHandler', 1)
        ];
    }

    /**
     * Trigger reconfigure command only once per action
     *
     * @param PackageEvent $event
     * @return void
     */
    public function reconfigureHandler(PackageEvent $event): void
    {
        $this->installer->postinstall($package);
    }

    /**
     * Expose capabilities
     *
     * @return string[]
     */
    public function getCapabilities(): array
    {
        return [
            'Composer\Plugin\Capability\CommandProvider' => 'Horde\Composer\CommandProvider',
        ];
    }
}
