<?php
declare(strict_types = 1);
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
     * Exposre which events are handled by which handler
     *
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-package-install' => 'postInstallHandler',
            'post-package-update' => 'postUpdateHandler',
        ];
    }

    /**
     * Handler for post-package-install
     *
     * @param PackageEvent $event
     * @return void
     */
    public function postInstallHandler(PackageEvent $event): void
    {
        $ops = $event->getOperations();
        foreach($ops as $op) {
            if ($op instanceof InstallOperation) {
                $package = $op->getPackage();
                try {
                    $this->installer->postinstall($package);
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * Handler for post-package-update
     *
     * @param PackageEvent $event
     * @return void
     */
    public function postUpdateHandler(PackageEvent $event): void
    {
        $ops = $event->getOperations();
        foreach($ops as $op) {
            if ($op instanceof UpdateOperation) {
                $package = $op->getTargetPackage();
                try {
                    $this->installer->postinstall($package);
                } catch (\Exception $e) {
                }
            }
        }
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
