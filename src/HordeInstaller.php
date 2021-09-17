<?php
declare(strict_types = 1);
namespace Horde\Composer;

use Composer\Factory;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use React\Promise\PromiseInterface;
use DirectoryIterator;
use ErrorException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Installer implementation for horde apps and themes
 *
 * @author Ralf Lang <lang@b1-systems.de>
 */
class HordeInstaller extends LibraryInstaller
{
    /**
     * @var string
     */
    protected string $projectRoot = '';
    /**
     * @var string
     */
    protected string $configDir = '';
    /**
     * @var string
     */
    protected string $appConfigDir = '';
    /**
     * @var string
     */
    protected string $hordeConfigDir = '';
    /**
     * @var string
     */
    protected string $webDir = '';

    /**
     * @protected string $presetDir
     *
     * A location to look for preset files in the deployment
     */
    protected string $presetDir = '';
    /**
     * @protected string
     */
    protected string $jsDir = '';
    /**
     * @protected string
     */
    protected string $hordeWebDir = '';
    /**
     * @protected string
     */
    protected string $configRegistryDir = '';
    /**
     * @protected string
     */
    protected string $packageDir = '';

    /**
     * A location to look for package-supplied registry snippets
     * This is useful for custom apps
     *
     * @protected string
     */
    protected string $packageDocRegistryDir = '';
    /**
     * @protected string
     */
    protected string $packageName = '';
    /**
     * @protected string
     */
    protected string $vendorName = '';

    protected function setupDirs(PackageInterface $package): void
    {
        [$this->vendorName, $this->packageName] = explode('/', $package->getName(), 2);
        $this->projectRoot = realpath(dirname(Factory::getComposerFile()));
        $this->webDir = $this->projectRoot . '/web';
        $this->configDir = $this->projectRoot . '/var/config';
        $this->hordeConfigDir = $this->projectRoot . '/var/config/horde';
        $this->appConfigDir = $this->projectRoot . '/var/config/' . $this->packageName;
        $this->hordeWebDir = $this->webDir . '/horde';
        $this->configRegistryDir = $this->configDir . '/horde/registry.d/';
        $this->jsDir = $this->webDir . '/js';

        switch ($package->getType()) {
            case 'horde-application':
                $this->packageDir = $this->webDir . '/' . $this->packageName;
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
     * @return string
     */
    public function getInstallPath(PackageInterface $package): string
    {
        $this->setupDirs($package);

        return $this->packageDir;
    }

    /**
     * Handle horde-specific postinstall tasks
     *
     * @param InstalledRepositoryInterface $repo  The repository
     * @param PackageInterface $package  The package installed or updated
     */
    public function postinstall(PackageInterface $package): void
    {
        if (!$this->supports($package->getType())) {
            return;
        }
        $this->setupDirs($package);
        $app = $this->packageName;

        // In case of a horde-application
        if ($package->getType() == 'horde-application') {
            // Create missing dirs
            if (!file_exists($this->hordeConfigDir)) {
                mkdir($this->hordeConfigDir, 0750, true);
            }
            if (!file_exists($this->configRegistryDir)) {
                mkdir($this->configRegistryDir, 0750, true);
            }
            if (!file_exists($this->appConfigDir)) {
                mkdir($this->appConfigDir, 0750, true);
            }
            // Type horde-application needs a config/horde.local.php pointing to horde dir
            // If a horde-application has a registry snippet in doc-dir, fetch it and put it into config/registry.d
            if (is_dir($this->packageDocRegistryDir)) {
                $dir = new DirectoryIterator($this->packageDocRegistryDir);
                foreach ($dir as $entry) {
                    if ($dir->isFile()) {
                        copy($dir->getPathName(), $this->configRegistryDir . $entry);
                    }
                }
            }
            // If a deployment has a preset dir for this app, copy files from preset
            if (is_dir($this->presetDir)) {
                // TODO: Do we need a RecursiveDirectoryInterator here?
                $dir = new DirectoryIterator($this->presetDir);
                foreach ($dir as $entry) {
                    if ($dir->isFile() && !file_exists($this->appConfigDir . '/' . $entry)) {
                        copy($dir->getPathName(), $this->appConfigDir . '/' . $entry);
                    }
                }
            }

            $this->linkJavaScript($package, $this->packageName);
            $hordeLocalFilePath = $this->appConfigDir . '/horde.local.php';
            $hordeLocalFileContent = sprintf(
                "<?php if (!defined('HORDE_BASE')) define('HORDE_BASE', '%s');\n",
                $this->hordeWebDir
            );
            // special case horde/horde needs to require the composer autoloader
            if ($package->getName() == 'horde/horde') {
                $hordeLocalFileContent .= $this->_legacyWorkaround(realpath($this->vendorDir));
                $hordeLocalFileContent .= "require_once('" . $this->vendorDir ."/autoload.php');";

                // ensure a registry snippet for base exists. If not, create one containing only fileroot
                $registryLocalFilePath = $this->configRegistryDir . '/00-horde.php';
                if (!file_exists($registryLocalFilePath)) {
                    $registryLocalFileContent = sprintf(
                        '<?php
$deployment_webroot = \'%s\';
$deployment_fileroot = \'%s\';
$app_fileroot = \'%s\';
$app_webroot = \'%s\';
',
                        '/',
                        $this->webDir,
                        $this->hordeWebDir,
                        '/horde'

                    );
                    $registryLocalFileContent .=
                    '$this->applications[\'horde\'][\'fileroot\'] = $app_fileroot;' . PHP_EOL .
                    '$this->applications[\'horde\'][\'webroot\'] = $app_webroot;' . PHP_EOL .
                    '$this->applications[\'horde\'][\'jsfs\'] = $deployment_fileroot . \'/js/horde/\';' . PHP_EOL .
                    '$this->applications[\'horde\'][\'jsuri\'] = $deployment_webroot . \'js/horde/\';' . PHP_EOL .
                    '$this->applications[\'horde\'][\'themesfs\'] = $deployment_fileroot . \'/themes/horde/\';' . PHP_EOL .
                    '$this->applications[\'horde\'][\'themesuri\'] = $deployment_webroot . \'/themes/horde/\';';
                    file_put_contents($registryLocalFilePath, $registryLocalFileContent);
                }
            } else {
                // A registry snippet should ensure the install dir is known
                $registryAppFilename = $this->configRegistryDir . 'location-' . $app . '.php';
                $registryAppSnippet = '<?php' . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'fileroot\'] = "$deployment_fileroot/' . $app . '";' . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'webroot\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../' . $app . "';"  . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'themesfs\'] = $this->applications[\'horde\'][\'fileroot\'] . \'/../themes/' . $app . '/\';' . PHP_EOL .
                  '$this->applications[\'' . $app . '\'][\'themesuri\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../themes/' . $app . '/\';';
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
        // Run the ThemesHandler
        $themes = new ThemesHandler($this->filesystem, $this->projectRoot);
        if ($package->getType() == 'horde-theme') {
            // register
            $themes->themesCatalog->register(
                $this->vendorName,
                $this->packageName,
                $this->packageDir
            );
        }
        $themes->setupThemes();
        $this->linkVarConfig();
    }

    /**
     * Symlink contents of var/config
     *
     * We always check the whole tree even though this may happen
     * multiple times in installations with many apps
     * 
     * @return void
     */
    public function linkVarConfig(): void
    {
        // Abort unless var/config exists and is readable
        if (!is_dir($this->configDir) || !is_readable($this->configDir)) {
            return;
        }
        // Iterate through subdirs
        foreach (new DirectoryIterator($this->configDir) as $appFileInfo) {
            if (!$appFileInfo->isDir()) {
                continue;
            }
            if ($appFileInfo->isDot()) {
                continue;
            }
            $app = $appFileInfo->getFilename();
            // Next if no corresponding web/$app/config dir exists
            $appConfigDir = $appFileInfo->getPathname();
            $targetDir = $this->webDir . '/' . $app . '/config/';
            if (!is_dir($targetDir)) {
                continue;
            }
            // Iterate recursively
            $contentInfo = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appConfigDir));
            foreach ($contentInfo as $contentItem) {
                // Don't symlink dirs
                if ($contentItem->isDir()) {
                    continue;
                }
                $relativeName = $contentInfo->getSubPathname();
                $linkName = $targetDir . '/' . $relativeName;
                $sourceName = $appConfigDir . '/' . $relativeName;
                if (file_exists($linkName)) {
                    continue;
                }
                symlink($sourceName, $linkName);
            }
            // Do not overwrite existing files or links
        }
    }

    public function linkJavaScript($package, $app = 'horde'): void
    {
        // TODO: Error handling
        $packageJsDir = $this->getInstallPath($package) . '/js/';
        if (!is_dir($packageJsDir)) {
            return;
        }

        try {
            $jsDirHandle = opendir($packageJsDir);
        } catch (ErrorException $errorException) {
            return;
        }

        $targetDir = $this->jsDir . '/' . $app;
        $this->filesystem->ensureDirectoryExists($targetDir);
        while (false !== ($sourceItem = readdir($jsDirHandle))) {
            if ($sourceItem == '.' || $sourceItem == '..') {
                continue;
            }

            $this->filesystem->relativeSymlink(realpath(sprintf('%s/%s', $packageJsDir, $sourceItem)),  sprintf('%s/%s', $targetDir, $sourceItem));
        }

        closedir($jsDirHandle);
    }

    // Work around case inconsistencies, hard requires etc until they are resolved in code
    protected function _legacyWorkaround($path): string
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
