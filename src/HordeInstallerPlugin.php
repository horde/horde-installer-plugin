<?php
declare(strict_types = 1);
namespace Horde\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;

class HordeInstallerPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $installer;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->installer = new HordeInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($this->installer);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        $composer->getInstallationManager()->removeInstaller($this->installer);
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
        return;
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-package-install' => 'postInstallHandler',
            'post-package-update' => 'postUpdateHandler',
        ];
    }

    public function postInstallHandler($event)
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

    public function postUpdateHandler($event)
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
