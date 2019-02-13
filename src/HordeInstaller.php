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
                if ($package->getName() == 'horde/horde') {
                    return 'horde-dir/';
                }
                return 'horde-dir/apps/' . $package->getPrettyName();
            break;
            case 'horde-library':
                return 'horde-dir/libs/' . $package->getPrettyName();
            break;
            case 'horde-theme':
                return 'horde-dir/themes/' . $package->getPrettyName();
            break;
            return 'not-found/';
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
