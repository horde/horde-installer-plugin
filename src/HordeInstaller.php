<?php

namespace Horde\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class HordeInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        switch ($package->getType())
        {
            case 'horde-application':
                return $package->getPrettyName();
            break;
            case 'horde-library':
                return 'libs/' . $package->getPrettyName();
            break;
            case 'horde-theme':
                return 'themes/' . $package->getPrettyName();
            break;
            return 'not-found/' . $package->getPrettyName();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        switch ($packageType)
        {
            case 'horde-application':
            case 'horde-library':
            case 'horde-theme':
              return true;
            default:
                return false;
        }
    }
}
