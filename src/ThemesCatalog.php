<?php
declare(strict_types = 1);
namespace Horde\Composer;
use \json_decode;
use \file_get_contents;
use \DirectoryIterator;

/**
 * Encapsulate handling the themes catalog
 */
class ThemesCatalog
{
    protected $rootDir;
    protected $catalog;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->themesFile = $rootDir . '/themes.json';
        if (is_file($this->themesFile)) {
            $this->catalog = json_decode(
                file_get_contents(
                    $this->themesFile
                ),
                true
            );
        } else {
            $this->catalog = [];
        }
    }

    public function save()
    {
        file_put_contents($this->themesFile, json_encode($this->catalog, JSON_PRETTY_PRINT));
    }


    public function register(
        string $vendorName,
        string $packageName,
        string $installDir
    ) {
        /**
         * Convention: 
         * 
         * Root themes package names start with theme-
         * Addon themes packages start with apptheme-$app
         * 
         * TODO: Mechanism to override this using a json file
         */

        if (substr($packageName, 0, 6) == 'theme-') {
            $type = 'rootThemes';
            $themeName = substr($packageName, 6);
        }
        $dir = new DirectoryIterator($installDir);
        foreach ($dir as $entry) {
            if (!$entry->isDir() ||
                $entry->isDot() ||
                $entry->getFilename()[0] == '.'
            ) {
                continue;
            }
            $app = $entry->getFilename();
            $this->catalog[$themeName][$app] = [
                'provider' => $installDir,
                'linkDir' => $entry->getPathname(),
                'themeName' => $themeName,
                'packageName' => $packageName,
                'vendorName' => $vendorName
            ];
        }
        $this->save();
    }

    public function unregister()
    {
        $this->save();
    }

    public function toArray()
    {
        return $this->catalog;
    }
}