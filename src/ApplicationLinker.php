<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\InstalledVersions;
use Composer\Util\Filesystem;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

class ApplicationLinker
{
    private string $baseDir;
    private string $mode;
    private Filesystem $filesystem;
    /**
     * List of packages considered as apps
     *
     * @var string[]
     */
    private array $appPackages;

    /**
     * @param Filesystem $filesystem Filesystem helper
     * @param string[] $appPackages List of vendor/app strings
     * @param string   $baseDir root package dir
     * @param string   $mode    Defaults to symlink
     */
    public function __construct(Filesystem $filesystem, array $appPackages, string $baseDir, string $mode = 'symlink')
    {
        $this->baseDir = $baseDir;
        $this->filesystem = $filesystem;
        $this->appPackages = $appPackages;
        $this->mode = $mode;
    }
    /**
     * Symlink contents of applications to web dir
     *
     * We always check the whole tree even though this may happen
     * multiple times in installations with many apps
     *
     * @return void
     */
    public function run(): void
    {
        $webDir = $this->baseDir . '/web';
        // Custom vendor dir is not currently supported.
        $vendorDir = $this->baseDir . '/vendor';
        // Ensure we have a webdir
        $this->filesystem->ensureDirectoryExists($webDir);
        // Ensure we have a static dir for ephemeral, generated files ...
        $this->filesystem->ensureDirectoryExists($webDir . '/static');
        // TODO: Move implementations to separate classes
        foreach ($this->appPackages as $app) {
            if ($app === 'horde/components') {
                continue;
            }
            $appVendorDir = $vendorDir . '/' . $app;
            [$vendor, $appName] = explode('/', $app);
            $appWebDir = $webDir . '/' . $appName;
            // abort if the app isn't actually there
            if (!is_dir($appVendorDir) || !is_readable($appVendorDir)) {
                // TODO: Consume IO object and warn
                continue;
            }
            // create the app's main dir in the web/ tree
            $this->filesystem->ensureDirectoryExists($appWebDir);
            // Items we won't copy or link to the web tree
            $filterList = [
                'files' => [
                    'LICENSE', 'composer.json', 'composer.lock', '.gitattributes',
                    '.horde.yml', '.travis.yml', 'package.xml', 'phpunit.xml.dist',
                    '.gitignore', 'README.rst', 'README.md', 'README', 'CHANGELOG.md',
                    '.php-cs-fixer.dist.php', '.php-cs-fixer.cache', 'phpunit.xml',
                ],
                'dirs' => [
                    'doc',
                    'test',
                    'bin',
                    'script',
                    'scripts',
                    'static', // static should be ensured to exist in webdir.
                    '.git',
                    '.github',
                ],
            ];

            if ($this->mode === 'symlink') {
                // create links to the app's subdirs and files in the web/ tree, omitting select dirs and files
                foreach (new DirectoryIterator($appVendorDir) as $appFileInfo) {
                    if ($appFileInfo->isDot()) {
                        continue;
                    }
                    $name = $appFileInfo->getFilename();
                    if ($appFileInfo->isDir()) {
                        if (in_array(
                            $name,
                            $filterList['dirs']
                        )) {
                            continue;
                        }
                        $this->filesystem->relativeSymlink(
                            $appVendorDir . '/' . $name,
                            $appWebDir . '/' . $name
                        );
                    }
                    if (in_array(
                        $name,
                        $filterList['files']
                    )) {
                        continue;
                    }
                    $this->filesystem->relativeSymlink(
                        $appVendorDir . '/' . $name,
                        $appWebDir . '/' . $name
                    );
                }
            } elseif ($this->mode == 'proxy') {
                // This list is different from the one for the linker
                $filterList = [

                    'files' => [
                        '.gitignore',
                        '.gitattributes',
                        'README.rst',
                        'README',
                        'LICENSE',
                        'phpunit.xml',
                        'phpunit.xml.dist',
                        'composer.json',
                    ],
                    'dirs' => [
                        'bin',
                        'lib',
                        'src',
                        '.git',
                        '.github',
                        'doc',
                        'js',
                        'script',
                        'scripts',
                        'static',
                        'config',
                        'locale',
                        'themes',
                        'templates',
                        'vendor',
                    ],
                ];
                $this->filesystem->emptyDirectory($appWebDir, true);
                // We are already per-app
                // appWebDir and appVendorDir are already set
                $r = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appVendorDir));
                foreach ($r as $f => $entry) {
                    // Ignore directories as such - they are created if they have relevant files
                    if ($entry->isDir()) {
                        continue;
                    }
                    $name = $entry->getFilename();
                    $relativePathName = $r->getSubPathname();
                    $relativePath = $r->getSubPath();
                    $split = explode(DIRECTORY_SEPARATOR, $relativePathName, 3);
                    // Skip subpaths of the filtered dirs
                    if (in_array(
                        $split[0],
                        $filterList['dirs']
                    )) {
                        continue;
                    }
                    if (in_array(
                        $name,
                        $filterList['files']
                    )) {
                        continue;
                    }
                    $this->filesystem->ensureDirectoryExists($appWebDir . DIRECTORY_SEPARATOR . $relativePath);
                    $reflection = new ReflectionMethod($this->filesystem, 'findShortestPath');
                    if ($reflection->getNumberOfParameters() >= 4) {
                        $pathProxyToAutoloader = $this->filesystem->findShortestPath(
                            $appWebDir . DIRECTORY_SEPARATOR . $relativePathName,
                            $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php',
                            preferRelative: true
                        );
                        $pathProxyToFile = $this->filesystem->findShortestPath(
                            $appWebDir . DIRECTORY_SEPARATOR . $relativePathName,
                            $appVendorDir . DIRECTORY_SEPARATOR . $relativePathName,
                            preferRelative: true
                        );
                    } else {
                        // Older composer versions don't support preferRelative
                        $pathProxyToAutoloader = $this->filesystem->findShortestPath(
                            $appWebDir . DIRECTORY_SEPARATOR . $relativePathName,
                            $vendorDir . DIRECTORY_SEPARATOR . 'autoload.php',
                        );
                        $pathProxyToFile = $this->filesystem->findShortestPath(
                            $appWebDir . DIRECTORY_SEPARATOR . $relativePathName,
                            $appVendorDir . DIRECTORY_SEPARATOR . $relativePathName,
                        );                        
                    }


                    $originalContent = file_get_contents($appVendorDir . DIRECTORY_SEPARATOR . $relativePathName);
                    if (str_contains((string) $originalContent, '<?php')) {
                        // PHP files get a proxy
                        $content = "<?php\nrequire_once(__DIR__ . '/$pathProxyToAutoloader');\nrequire_once(__DIR__ . '/$pathProxyToFile');";
                    } else {
                        // Non-PHP files get copied as is
                        $content = $originalContent;
                    }
                    $this->filesystem->filePutContentsIfModified(
                        $appWebDir . DIRECTORY_SEPARATOR . $relativePathName,
                        $content
                    );
                } // EndForEach File
            } else {
                $copy = new RecursiveCopy($appVendorDir, $appWebDir, array_merge($filterList['files'], $filterList['dirs']));
                $copy->copy();
            }
        } // EndForEach App
    }
}
