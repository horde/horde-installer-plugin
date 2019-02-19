<?php

namespace Horde\Composer;

use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;

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
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
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
    protected function postinstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        // Type horde-application needs a config/horde.local.php pointing to horde dir
        // If a horde-application has a registry snippet in doc-dir, fetch it and put it into config/registry.d
        // horde-library needs to check for js/ to copy or link
        list($vendor, $app) = explode('/', $package->getName(), 2);
        if ($package->getType() == 'horde-application')
        {
            $this->linkJavaScript($package, $app);
            $hordeLocalFilePath = $this->getInstallPath($package) . '/config/horde.local.php';
            $hordeLocalFileContent = sprintf("<?php if (!defined('HORDE_BASE')) define('HORDE_BASE', '%s');",
                realpath(dirname($this->getInstallPath($package), 2) . '/horde/horde/') );
            // special case horde/horde needs to require the composer autoloader
            if ($package->getName() == 'horde/horde') {
                $hordeLocalFileContent .= $this->_legacyWorkaround(realpath($this->vendorDir));
                $hordeLocalFileContent .= 'require_once(\'' . $this->vendorDir .'/autoload.php\');';

                // ensure a registry.local.php exists. If not, create one containing only fileroot
                $registryLocalFilePath = $this->getInstallPath($package) . '/config/registry.local.php';
                if (!file_exists($registryLocalFilePath))
                {
                    $registryLocalFileContent = sprintf(
                        "<?php\n\$app_fileroot = '%s';\n// \$app_webroot = \$this->detectWebroot();\n",
                        realpath($this->getInstallPath($package))
                    );
                    $registryLocalFileContent .= 
                    '$this->applications[\'horde\'][\'jsfs\'] = $this->applications[\'horde\'][\'fileroot\'] . \'/../js/horde/\';' .
                    '$this->applications[\'horde\'][\'jsuri\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../js/horde/\';';

                    file_put_contents($registryLocalFilePath, $registryLocalFileContent);
                }
            } else {
                // A registry snippet should ensure the install dir is known
                $registryAppFilename = dirname($this->getInstallPath($package), 2) . '/horde/horde/config/registry.d/location-' . $app . '.php';
                // TODO: Do not overwrite user-provided files
                // TODO: If the app provides an own snippet in /doc/, amend
                $registryAppSnippet = '<?php ' .
                  '$this->applications[\'' . $app . '\'][\'fileroot\'] = dirname(__FILE__, 4) . \'/' . $app . '\';' .
                  '$this->applications[\'' . $app . '\'][\'webroot\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../' . $app . '\';';
                file_put_contents($registryAppFilename, $registryAppSnippet);
            }
            file_put_contents($hordeLocalFilePath, $hordeLocalFileContent);
        }
        // horde-library needs to check for js/ to copy or link
        if ($package->getType() == 'horde-library')
        {
            $this->linkJavaScript($package);
        }
    }

    public function linkJavaScript($package, $app = 'horde')
    {
        $jsDir = $this->getInstallPath($package) . '/js/';
        // TODO: Error handling
        if (!is_dir($jsDir)) {
            return;
        }
        try {
            $jsDirHandle = opendir($jsDir);
        } catch (ErrorException $e) {
            return;
        }
        $projectRoot = realpath(dirname(\Composer\Factory::getComposerFile()));
        $targetDir = $projectRoot . '/horde/js/' . $app;
        $this->filesystem->ensureDirectoryExists($targetDir);
        while (false !== ($sourceItem = readdir($jsDirHandle))) {
            if ($sourceItem == '.' || $sourceItem == '..')
            {
                continue;
            }
            $this->filesystem->relativeSymlink(realpath("$jsDir/$sourceItem"),  "$targetDir/$sourceItem");
        }
        closedir($jsDirHandle);
    }

    // Work around case inconsistencies, hard requires etc until they are resolved in code
    protected function _legacyWorkaround($path)
    {
        return sprintf("ini_set('include_path', '%s/horde/autoloader/lib%s%s/horde/form/lib/%s' .  ini_get('include_path'));
        require_once('%s/horde/core/lib/Horde/Core/Nosql.php');
        ",
            $path,
            PATH_SEPARATOR,
            $path,
            PATH_SEPARATOR,
            $path
        );
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
