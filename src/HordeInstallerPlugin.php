<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;
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
     * @return array<string, array<int, int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        $events = [];
        if (method_exists('HordeInstaller', 'reconfigure')) {
            $events['post-autoload-dump'] = ['reconfigureHandler', 1];
        }
        return $events;
    }

    /**
     * Trigger reconfigure command only once per action
     *
     * @param Event $event
     * @return void
     */
    public function reconfigureHandler(Event $event): void
    {
        $this->installer->reconfigure();
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
