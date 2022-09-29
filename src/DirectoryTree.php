<?php

declare(strict_types=1);

namespace Horde\Composer;

use Composer\Util\Filesystem;
use DirectoryIterator;
use ErrorException;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DirectoryTree
{
    private ?string $rootPackageDir = null;
    private ?string $vendorDir = null;
    private ?string $binDir = null;
    private ?string $presetDir = null;
    private ?string $webDir = null;
 
    public static function fromComposerJsonPath(string $path): self
    {
        if (!is_file($path)) {
            throw new Exception('Expected full path of composer.json file');
        }
        $json = json_decode(file_get_contents($path));
        if (!is_object($json)) {
            throw new Exception('Could not parse json file');
        }
        $self = new self($path);
        // TODO: Extract data from composer.json, if present
        return $self;
    }

    public function __construct(string $rootPackageDir)
    {
        $this->withRootPackageDir($rootPackageDir);
    }
    public function withRootPackageDir(string $dir): self
    {
        $this->rootPackageDir = '';
        if ($dir === '' || $dir[0] !== '/') {
            $this->rootPackageDir = getcwd();
        }
        if ($dir) {
            $this->rootPackageDir . '/' . $dir;
        }
        return $this;
    }

    public function withVendorDir(string $dir): self
    {
        $this->vendorDir = $dir;
        return $this;
    }

    public function withBinDir(string $dir): self
    {
        $this->vendorDir = $dir;
        return $this;
    }

    public function getRootPackageDir(): string
    {
        if ($this->rootPackageDir) {
            return $this->rootPackageDir;
        }
        if ($this->vendorDir) {
            $likelyRoot = dirname($this->vendorDir);
            if (is_file($likelyRoot . '/composer.json')) {
                return $likelyRoot;
            }
        }
        throw new Exception('Could not determine RootPackageDir');
    }

    public function getVendorDir(): string
    {
        if ($this->vendorDir) {
            return $this->vendorDir;
        }
        $root = $this->getRootPackageDir();
        $likelyVendorDir = $root . '/vendor';
        if (is_dir($likelyVendorDir)) {
            return $likelyVendorDir;
        }
        throw new Exception('Could not determine VendorDir');
    }

    public function getVendorSpecificDir(string $vendor): string
    {
        return $this->getVendorDir() . '/' . $vendor;
    }

    public function getVendorPackageDir(string $vendor, string $package): string
    {
        return $this->getVendorDir() . '/' . $vendor . '/' . $package;
    }

    /**
     * List all vendors from which a package is installed
     *
     * @return iterable<string>
     */
    public function getVendors(): iterable
    {
        $vendors = [];
        foreach (new DirectoryIterator($this->getVendorDir()) as $dir) {
            if ($dir->isDot() || !$dir->isDir() || in_array($dir->getFileName(), ['bin'])) {
                continue;
            }
            $vendors[] = $dir->getFileName();
        }
        return $vendors;
    }

    /**
     * List packages installed from a specific vendor
     *
     * @param string $vendor
     * @return iterable<string> A list of packages in a vendor-specific dir
     */
    public function getPackagesByVendor(string $vendor): iterable
    {
        $packages = [];
        foreach (new DirectoryIterator($this->getVendorDir() . '/' . $vendor) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) {
                continue;
            }
            $packages[] = $dir->getFileName();
        }
        return $packages;
    }

    public function getDependencyDir(string $vendor, string $package): string
    {
        return $this->getVendorSpecificDir($vendor) . '/' . $package;
    }

    public function getVarConfigDir(): string
    {
        return $this->getRootPackageDir() . '/var/config';
    }

    public function getWebReadableRootDir(): string
    {
        return $this->webDir ?? $this->getRootPackageDir() . '/web';
    }

    public function getBinDir(): string
    {
        return $this->binDir ?? $this->getVendorDir() . '/bin';
    }

    public function getPresetDir(): string
    {
        return $this->presetDir ?? $this->getRootPackageDir() . '/presets';
    }
}