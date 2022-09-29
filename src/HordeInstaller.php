<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Factory;
use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use ErrorException;
use Horde\Composer\IOAdapter\ComposerIoAdapter;
use React\Promise\PromiseInterface;

/**
 * Installer implementation for horde apps and themes
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */
class HordeInstaller extends LibraryInstaller
{
    /**
     * Handle horde-specific postinstall tasks
     */
    public function reconfigure(): void
    {
        $mode = \strncasecmp(\PHP_OS, 'WIN', 3) === 0 ? 'copy' : 'symlink';
        $flow = HordeReconfigureFlow::fromComposer($this->composer, new ComposerIoAdapter($this->io));
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
                /* [$vendorName, $packageName] = explode('/', $package->getName(), 2);
                   $projectRoot = (string)realpath(dirname(Factory::getComposerFile()));
                   return $projectRoot . '/web/' . $packageName;*/
            case 'horde-library':
            default:
                return parent::getInstallPath($package);
        }
    }

    /**
     * {@inheritDoc}
     * @param string $packageType
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
