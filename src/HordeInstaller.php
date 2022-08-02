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
        $this->setupDirs($package);
        $app = $this->packageName;
        $flow = new HordeReconfigureFlow(new ComposerIoAdapter($this->io), $this->composer);
        $flow->run();
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
