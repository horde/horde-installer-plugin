<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;

class HordeLocalFileWriter
{
    /**
     * @var string[]
     */
    private array $apps;
    private string $configDir;
    private string $vendorDir;
    private string $webDir;
    private string $vendorHordeDir;
    private Filesystem $filesystem;
    private TemplateRenderer $renderer;
    private string $templateDir;

    /**
     * @param Filesystem $filesystem
     * @param string $baseDir
     * @param string[] $apps
     */
    public function __construct(Filesystem $filesystem, private string $baseDir, array $apps, private string $mode = 'proxy')
    {
        $this->filesystem = $filesystem;
        $this->configDir = $baseDir . '/var/config';
        $this->vendorDir = $baseDir . '/vendor';
        $this->vendorHordeDir = $this->vendorDir . DIRECTORY_SEPARATOR . 'horde' . DIRECTORY_SEPARATOR . 'horde';
        $this->webDir = $baseDir . '/web';
        $this->apps = $apps;
        $this->renderer = new TemplateRenderer();
        $this->templateDir = dirname(__DIR__) . '/templates/horde-local';
    }

    public function run(): void
    {
        foreach ($this->apps as $app) {
            $this->processApp($app);
        }
    }

    private function processApp(string $app): void
    {
        $hordeWebDir = $this->webDir . '/horde';
        [$vendor, $name] = explode('/', $app, 2);
        $this->filesystem->ensureDirectoryExists($this->configDir . "/$name");
        $path = $this->configDir . "/$name/horde.local.php";
        $hordeBaseDir = $hordeWebDir;
        if ($this->mode === 'proxy') {
            $hordeBaseDir = $this->vendorHordeDir;
        }

        $ds = DIRECTORY_SEPARATOR;
        $appNameUpper = strtoupper($name);
        $templatesDir = $this->vendorDir . $ds . $vendor . $ds . $name . $ds . 'templates';
        $autoloadExtraFilePath = $this->baseDir . '/var/config/autoload-extra.php';

        $vars = [
            'hordeBaseDir' => $hordeBaseDir,
            'configDir' => $this->configDir,
            'appNameUpper' => $appNameUpper,
            'templatesDir' => $templatesDir,
            'autoloadExtraFilePath' => file_exists($autoloadExtraFilePath) ? $autoloadExtraFilePath : null,
        ];

        if ($app == 'horde/horde') {
            $vars['legacyWorkaround'] = $this->_legacyWorkaround($this->filesystem->normalizePath($this->vendorDir));
            $vars['vendorDir'] = $this->vendorDir;
            $content = $this->renderer->render($this->templateDir . '/horde.php', $vars);
        } else {
            $content = $this->renderer->render($this->templateDir . '/app.php', $vars);
        }

        $this->filesystem->filePutContentsIfModified($path, $content);
    }

    /**
     * Legacy support
     *
     * Work around case inconsistencies
     * hard requires etc until they are resolved in code
     *
     * @param string $path Path to vendor dir
     */
    protected function _legacyWorkaround(string $path): string
    {
        return sprintf(
            "ini_set('include_path', '%s/horde/autoloader/lib%s%s/horde/form/lib/%s' .  ini_get('include_path'));\nrequire_once('%s/horde/core/lib/Horde/Core/Nosql.php');\n",
            $path,
            PATH_SEPARATOR,
            $path,
            PATH_SEPARATOR,
            $path,
        );
    }
}
