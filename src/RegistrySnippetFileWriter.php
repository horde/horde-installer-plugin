<?php

declare(strict_types=1);

namespace Horde\Composer;
use Composer\Util\Filesystem;

class RegistrySnippetFileWriter
{
    /**
     * List of apps
     *
     * @var string[]
     */
    private array $apps;
    private string $configDir;
    private string $configRegistryDir;
    private string $vendorDir;
    private string $webDir;

    private Filesystem $filesystem;

    /**
     * Undocumented function
     *
     * @param Filesystem $filesystem
     * @param string $baseDir
     * @param string[] $apps
     */
    public function __construct(Filesystem $filesystem, string $baseDir, array $apps)
    {
        $this->filesystem = $filesystem;
        $this->configDir = $baseDir . '/var/config';
        /**
         * The config dir for the registry snippets
         */
        $this->configRegistryDir = $this->configDir . '/horde/registry.d';
        $this->vendorDir = $baseDir . '/vendor';
        $this->webDir = $baseDir . '/web';
        $this->apps = $apps;
    }

    public function run(): void
    {
        // Ensure we have the base locations right
        $registry00FilePath = $this->configRegistryDir . '/00-horde.php';
        if (!file_exists($registry00FilePath)) {
            $registry00FileContent = sprintf(
                '<?php
$deployment_webroot = \'%s\';
$deployment_fileroot = \'%s\';
$app_fileroot = \'%s\';
$app_webroot = \'%s\';
',
                '/',
                $this->webDir,
                $this->webDir . '/horde',
                '/horde'
            );
            file_put_contents($registry00FilePath, $registry00FileContent);
        }

        // Ensure we have a base
        foreach ($this->apps as $app)
        {
            list($appVendor, $appName) = explode('/', $app);
            if ($app == 'horde/horde') {
                $registryAppFilename = $this->configRegistryDir . '/01-location-' . $appName . '.php';
                $registryAppSnippet =
                '$this->applications[\'horde\'][\'fileroot\'] = $app_fileroot;' . PHP_EOL .
                '$this->applications[\'horde\'][\'webroot\'] = $app_webroot;' . PHP_EOL .
                '$this->applications[\'horde\'][\'jsfs\'] = $deployment_fileroot . \'/js/horde/\';' . PHP_EOL .
                '$this->applications[\'horde\'][\'jsuri\'] = $deployment_webroot . \'js/horde/\';' . PHP_EOL .
                '$this->applications[\'horde\'][\'staticfs\'] = $deployment_fileroot . \'/static\';' . PHP_EOL .
                '$this->applications[\'horde\'][\'staticuri\'] = $deployment_webroot . \'static\';' . PHP_EOL .
                '$this->applications[\'horde\'][\'themesfs\'] = $deployment_fileroot . \'/themes/horde/\';' . PHP_EOL .
                '$this->applications[\'horde\'][\'themesuri\'] = $deployment_webroot . \'themes/horde/\';';
            } else {
                // A registry snippet should ensure the install dir is known
                $registryAppFilename = $this->configRegistryDir . '/02-location-' . $appName . '.php';
                $registryAppSnippet = '<?php' . PHP_EOL .
                '$this->applications[\'' . $appName . '\'][\'fileroot\'] = "$deployment_fileroot/' . $appName . '";' . PHP_EOL .
                '$this->applications[\'' . $appName . '\'][\'webroot\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../' . $app . "';"  . PHP_EOL .
                '$this->applications[\'' . $appName . '\'][\'themesfs\'] = $this->applications[\'horde\'][\'fileroot\'] . \'/../themes/' . $app . '/\';' . PHP_EOL .
                '$this->applications[\'' . $appName . '\'][\'themesuri\'] = $this->applications[\'horde\'][\'webroot\'] . \'/../themes/' . $app . '/\';';
            }
            if (!file_exists($registryAppFilename)) {
                file_put_contents($registryAppFilename, $registryAppSnippet);
            }
        }
    }    
}
