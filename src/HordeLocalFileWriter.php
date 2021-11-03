<?php
declare(strict_types = 1);
namespace Horde\Composer;
use \Composer\Util\Filesystem;

class HordeLocalFileWriter
{
    /**
     * List of apps
     *
     * @var string[]
     */
    private array $apps;
    private string $configDir;
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
        $this->vendorDir = $baseDir . '/vendor';
        $this->webDir = $baseDir . '/web';
        $this->apps = $apps;
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
        list($vendor, $name) = explode('/', $app, 2);
        $this->filesystem->ensureDirectoryExists($this->configDir . "/$name");
        $path = $this->configDir . "/$name/horde.local.php";
        $hordeLocalFileContent = sprintf(
            "<?php if (!defined('HORDE_BASE')) define('HORDE_BASE', '%s');\n",
            $hordeWebDir
        );
        // special case horde/horde needs to require the composer autoloader
        if ($app == 'horde/horde') {
            $hordeLocalFileContent .= $this->_legacyWorkaround(realpath($this->vendorDir));
            $hordeLocalFileContent .= "require_once('" . $this->vendorDir ."/autoload.php');";
        }
        $this->filesystem->filePutContentsIfModified($path, $hordeLocalFileContent);
    }
    /**
     * Legacy support
     * 
     * Work around case inconsistencies
     * hard requires etc until they are resolved in code
     *
     * @param string $path Path to vendor dir
     * @return string
     */
    protected function _legacyWorkaround(string $path): string
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
}