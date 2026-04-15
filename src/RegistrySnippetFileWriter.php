<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;

class RegistrySnippetFileWriter
{
    /**
     * @var string[]
     */
    private array $apps;
    private string $configDir;
    private string $configRegistryDir;
    private string $webDir;
    private TemplateRenderer $renderer;
    private string $templateDir;

    /**
     * @param Filesystem $filesystem
     * @param string $baseDir
     * @param string[] $apps
     */
    public function __construct(
        private Filesystem $filesystem,
        private string $baseDir,
        array $apps,
        private ReconfigureOptions $options = new ReconfigureOptions(),
    ) {
        $this->configDir = $baseDir . '/var/config';
        $this->configRegistryDir = $this->configDir . DIRECTORY_SEPARATOR . 'horde' . DIRECTORY_SEPARATOR . 'registry.d';
        $this->webDir = $baseDir . DIRECTORY_SEPARATOR . 'web';
        $this->apps = $apps;
        $this->renderer = new TemplateRenderer();
        $this->templateDir = dirname(__DIR__) . '/templates/registry';
    }

    public function run(): void
    {
        $ds = DIRECTORY_SEPARATOR;
        $webrootUri = $this->options->webroot;
        $registry00FilePath = $this->configRegistryDir . '/00-horde.php';

        if (!file_exists($registry00FilePath)) {
            $content = $this->renderer->render($this->templateDir . '/00-horde.php', [
                'mode' => $this->options->mode,
                'webrootUri' => $webrootUri,
                'appFileroot' => $this->webDir . $ds . 'horde',
                'appWebroot' => '/horde',
            ]);
            $this->filesystem->filePutContentsIfModified($registry00FilePath, $content);
        }

        $webrootOverrideFilePath = $this->configRegistryDir . '/03-override-webroots.php';
        if (!file_exists($webrootOverrideFilePath)) {
            $content = $this->renderer->render($this->templateDir . '/03-override-webroots.php', [
                'mode' => $this->options->mode,
                'apps' => $this->apps,
            ]);
            $this->filesystem->filePutContentsIfModified($webrootOverrideFilePath, $content);
        }

        foreach ($this->apps as $app) {
            [$appVendor, $appName] = explode('/', $app);
            $appInVendorDir = $this->baseDir . $ds . 'vendor' . $ds . $appVendor . $ds . $appName . $ds;

            if ($app == 'horde/horde') {
                $registryAppFilename = $this->configRegistryDir . '/01-location-' . $appName . '.php';
                $content = $this->renderer->render($this->templateDir . '/01-location-horde.php', [
                    'appInVendorDir' => $appInVendorDir,
                    'webrootUri' => $webrootUri,
                    'webDir' => $this->webDir,
                    'ds' => $ds,
                ]);
            } else {
                $registryAppFilename = $this->configRegistryDir . '/02-location-' . $appName . '.php';
                $content = $this->renderer->render($this->templateDir . '/02-location-app.php', [
                    'appName' => $appName,
                    'appInVendorDir' => $appInVendorDir,
                    'webrootUri' => $webrootUri,
                    'webDir' => $this->webDir,
                    'ds' => $ds,
                ]);
            }

            // Some versions of the middleware router require the routes.php file to exist even if empty
            $routesFilePath = $appInVendorDir . 'config' . $ds . 'routes.php';
            if (is_dir(dirname($routesFilePath)) && !file_exists($routesFilePath)) {
                $this->filesystem->filePutContentsIfModified(
                    $routesFilePath,
                    "<?php\n// Empty default routes file. Put custom routes into var/config/" . $appName . "/routes.local.php and run composer horde:reconfigure\n",
                );
            }
            $this->filesystem->filePutContentsIfModified($registryAppFilename, $content);
        }
    }
}
