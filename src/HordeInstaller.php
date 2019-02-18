<?php

namespace Horde\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

class HordeInstaller extends LibraryInstaller
{
    /**
     * {@inheritDoc}
     */
    public function getInstallPath(\Composer\Package\PackageInterface $package)
    {
        switch ($package->getType())
        {
            case 'horde-application':
                return $package->getPrettyName();
            break;
            case 'horde-library':
                return parent::getInstallPath($package);
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
    public function install(\Composer\Repository\InstalledRepositoryInterface $repo, \Composer\Package\PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->postinstall($repo, $package);
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        $this->postinstall($repo, $target);
    }

    /**
     * Handle horde-specific install/upgrade tasks
     *
     * @param \Composer\Repository\InstalledRepositoryInterface $repo  The repository
     * @param \Composer\Package\PackageInterface $package  The package installed or updated
     */
    protected function postinstall(\Composer\Repository\InstalledRepositoryInterface $repo, \Composer\Package\PackageInterface $package)
    {
        // Type horde-application needs a config/horde.local.php pointing to horde dir
        // If a horde-application has a registry snippet in doc-dir, fetch it and put it into config/registry.d
        // horde-library needs to check for js/ to copy or link

        if ($package->getType() == 'horde-application')
        {
            $hordeLocalFilePath = $this->getInstallPath($package) . '/config/horde.local.php';
            $hordeLocalFileContent = sprintf("<?php if (!defined('HORDE_BASE')) define('HORDE_BASE', '%s');",
                realpath(dirname($this->getInstallPath($package), 2) . '/horde/horde/') );
            // special case horde/horde needs to require the composer autoloader
            if ($package->getName() == 'horde/horde') {
                $hordeLocalFileContent .= 'require_once(\'' . $this->vendorDir .'/autoload.php\');';
                // ensure a registry.local.php exists. If not, create one containing only fileroot
                $registryLocalFilePath = $this->getInstallPath($package) . '/config/registry.local.php';
                if (!file_exists($registryLocalFilePath))
                {
                    $registryLocalFileContent = sprintf(
                        "<?php\n\$app_fileroot = '%s';\n// \$app_webroot = \$this->detectWebroot();\n",
                        realpath($this->getInstallPath($package))
                    );
                    file_put_contents($registryLocalFilePath, $registryLocalFileContent);
                }
            }
            file_put_contents($hordeLocalFilePath, $hordeLocalFileContent);
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
