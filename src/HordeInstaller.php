<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Factory;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use React\Promise\PromiseInterface;
use Horde\Composer\IOAdapter\ComposerIoAdapter;
use ErrorException;

/**
 * Installer implementation for horde apps and themes
 *
 * @author Ralf Lang <lang@b1-systems.de>
 */
class HordeInstaller extends LibraryInstaller
{
    /**
     * Handle horde-specific postinstall tasks
     *
     * @param PackageInterface $package  The package installed or updated
     */
    public function postinstall(PackageInterface $package): void
    {
        if (!$this->supports($package->getType())) {
            return;
        }
        $flow = new HordeReconfigureFlow(new ComposerIoAdapter($this->io), $this->composer);
        $flow->run();
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function getInstallPath(PackageInterface $package): string
    {
        switch ($package->getType()) {
            case 'horde-application':
                [$vendorName, $packageName] = explode('/', $package->getName(), 2);
                $projectRoot = (string)realpath(dirname(Factory::getComposerFile()));
                return $projectRoot . '/web/' . $packageName;
            case 'horde-library':
            default:
                return parent::getInstallPath($package);
        }
    }
    /**
     * {@inheritDoc}
     * @return bool
     */
    public function supports($packageType)
    {
        switch ($packageType) {
            case 'horde-application':
            case 'horde-library':
            case 'horde-theme':
                return true;

            default:
                return false;
        }
    }
}
