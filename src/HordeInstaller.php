<?php

namespace Horde\Composer;

use Composer\Factory;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use DirectoryIterator;
use ErrorException;

/**
 * Installer implementation for horde apps and themes
 *
 * @author Ralf Lang <lang@b1-systems.de>
 */
class HordeInstaller extends LibraryInstaller
{
    protected $projectRoot = '';
    protected $webDir = '';

    /**
     * @protected string $presetDir
     *
     * A location to look for preset files in the deployment
     */
    protected $presetDir = '';
    protected $jsDir = '';
    protected $hordeDir = '';
    protected $hordeRegistryDir = '';
    protected $packageDir = '';

    /**
     * A location to look for package-supplied registry snippets
     * This is useful for custom apps
     */
    protected $packageDocRegistryDir = '';
    protected $packageName = '';
    protected $vendorName = '';

    protected function _setupDirs(PackageInterface $package)
    {
        $this->projectRoot = realpath(dirname(Factory::getComposerFile()));
        $this->webDir = $this->projectRoot . '/web';
        $this->hordeDir = $this->webDir . '/horde';
        $this->hordeRegistryDir = $this->hordeDir . '/config/registry.d/';
        $this->jsDir = $this->webDir . '/js';
        list($this->vendorName, $this->packageName) = explode('/', $package->getName(), 2);

        switch ($package->getType()) {
            case 'horde-application':
                $this->packageDir = $this->webDir . '/' . $this->packageName;
                break;

            case 'horde-theme':
                $this->packageDir = $this->webDir . '/themes/' . $package->getPrettyName();
                break;

            case 'horde-library':
            default:
                $this->packageDir = parent::getInstallPath($package);
                break;
        }

        $this->packageDocRegistryDir = $this->packageDir . '/doc/registry.d/';
        $this->presetDir = $this->projectRoot . '/presets/' . $this->packageName;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package)
    {
        $this->_setupDirs($package);

        return $this->packageDir;
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
     * @param InstalledRepositoryInterface $repo  The repository
     * @param PackageInterface $package  The package installed or updated
     */
    protected function postinstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->_setupDirs($package);
        $app = $this->packageName;

        // Type horde-application needs a config/horde.local.php pointing to horde dir
        // If a horde-application has a registry snippet in doc-dir, fetch it and put it into config/registry.d
        if (is_dir($this->packageDocRegistryDir)) {
            $dir = new DirectoryIterator($this->packageDocRegistryDir);
            foreach ($dir as $entry) {
                if ($dir->isFile()) {
                    copy($dir->getPathName(), $this->hordeRegistryDir . $entry);
                }
            }
        }

        // If a deployment has a preset dir for this app, copy files from preset
        if (is_dir($this->presetDir)) {
            $dir = new DirectoryIterator($this->presetDir);
            foreach ($dir as $entry) {
                if ($dir->isFile() && !file_exists($this->packageDir . '/config/' . $entry)) {
                    copy($dir->getPathName(), $this->packageDir . '/config/' . $entry);
                }
            }
        }

        // horde-library needs to check for js/ to copy or link
        if ($package->getType() == 'horde-application') {
            $this->linkJavaScript($package, $this->packageName);
            $hordeLocalFilePath = $this->packageDir . '/config/horde.local.php';
            $hordeLocalFileContent = sprintf("<?php if (!defined('HORDE_BASE')) define('HORDE_BASE', '%s');\n",
            realpath( $this->hordeDir ));

            if ($package->getName() == 'horde/components') {
                // special case -  a horde app which does not need horde.
                // Do we need to generalize this for other standalone cases?
                return;
            }

            // special case horde/horde needs to require the composer autoloader
            if ($package->getName() == 'horde/horde') {
                $hordeLocalFileContent .= $this->_legacyWorkaround(realpath($this->vendorDir));
                $hordeLocalFileContent .= 'require_once(\'' . $this->vendorDir .'/autoload.php\');';

                // ensure a registry snippet for base exists. If not, create one containing only fileroot
                $registryLocalFilePath = $this->hordeDir . '/config/registry.d/00-horde.php';
                if (!file_exists($registryLocalFilePath)) {
                    $registryLocalFileContent = sprintf(
                        "<?php\n\$app_fileroot = '%s';\n" .
                        "\$app_webroot = '/horde';\n",
                        realpath($this->getInstallPath($package))
                    );
                    $registryLocalFileContent .=
                    '$this->applications[\'horde\'][\'fileroot\'] = $app_fileroot;' . PHP_EOL .
                    '$this->applications[\'horde\'][\'webroot\'] = $app_webroot;' . PHP_EOL .
                    '$this->applications[\'horde\'][\'jsfs\'] = $this->applications[\'horde\'][\'fileroot\'] . \'/../js/horde/\';' . PHP_EOL .
                    '$this->applications[\'horde\'][\'jsuri\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../js/horde/\';';
                    file_put_contents($registryLocalFilePath, $registryLocalFileContent);
                }
            } else {
                // A registry snippet should ensure the install dir is known
                $registryDir = $this->hordeDir . '/config/registry.d';
                if (!is_dir($registryDir)) {
                    mkdir($registryDir, 0775, true);
                }
                $registryAppFilename = $registryDir . '/location-' . $app . '.php';
                $registryAppSnippet = '<?php' . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'fileroot\'] = dirname(__FILE__, 4) . \'/' . $app . '\';' . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'webroot\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../' . $app . '\';';
                if (!file_exists($registryAppFilename)) {
                    file_put_contents($registryAppFilename, $registryAppSnippet);
                }
            }

            if (!file_exists($hordeLocalFilePath)) {
                file_put_contents($hordeLocalFilePath, $hordeLocalFileContent);
            }
        }

        // horde-library needs to check for js/ to copy or link
        if ($package->getType() == 'horde-library') {
            $this->linkJavaScript($package);
        }
    }

    public function linkJavaScript($package, $app = 'horde')
    {
        // TODO: Error handling
        $packageJsDir = $this->getInstallPath($package) . '/js/';
        if (!is_dir($packageJsDir)) {
            return;
        }

        try {
            $jsDirHandle = opendir($packageJsDir);
        } catch (ErrorException $e) {
            return;
        }

        $targetDir = $this->jsDir . '/' . $app;
        $this->filesystem->ensureDirectoryExists($targetDir);
        while (false !== ($sourceItem = readdir($jsDirHandle))) {
            if ($sourceItem == '.' || $sourceItem == '..') {
                continue;
            }

            $this->filesystem->relativeSymlink(realpath("$packageJsDir/$sourceItem"),  "$targetDir/$sourceItem");
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
