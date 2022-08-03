<?php

declare(strict_types=1);

namespace Horde\Composer;

use json_decode;
use file_get_contents;
use DirectoryIterator;
use Exception;

/**
 * Encapsulate handling the themes catalog
 */
class ThemesCatalog
{
    protected string $rootDir;
    /**
     * Nested structure
     *
     * @var array<mixed>
     */
    protected array $catalog;
    protected string $themesFile;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->themesFile = $rootDir . '/themes.json';
        if (is_file($this->themesFile)) {
            $content = file_get_contents($this->themesFile);
            if ($content === false) {
                throw new Exception('Could not read themes json file content');
            }
            $catalog = json_decode(
                $content,
                true
            );
            if (!is_array($catalog)) {
                throw new Exception('Invalid themes json file content');
            }
            $this->catalog = $catalog;
        } else {
            $this->catalog = [];
        }
    }

    public function save(): void
    {
        file_put_contents($this->themesFile, json_encode($this->catalog, JSON_PRETTY_PRINT));
    }


    public function register(
        string $vendorName,
        string $packageName,
        string $installDir
    ): void {
        /**
         * Convention:
         *
         * Root themes package names start with theme-
         * Addon themes packages start with apptheme-$app
         *
         * TODO: Mechanism to override this using a json file
         */
        $themeName = $packageName;
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

    public function unregister(): void
    {
        $this->save();
    }

    /**
     * Return an array representation
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->catalog;
    }
}
