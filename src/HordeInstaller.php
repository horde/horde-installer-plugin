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

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        // horde-application needs a config/horde.local.php pointing to horde dir
        // If a horde-application has a registry snippet in doc-dir, fetch it and put it into config/registry.d
        // special case horde/horde needs to require the composer autoloader
        // horde-library needs to check for js/ to copy or link
        // if horde/horde exists, ensure a registry.local.php exists. If not, create one containing only fileroot
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
