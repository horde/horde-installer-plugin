<?php

/**
 * Factor out the common workflow from the installer plugin and the reconfigure command
 * This simplifies code reuse
 */

declare(strict_types=1);

namespace Horde\Composer;

use Composer\InstalledVersions;
use Composer\PartialComposer;
use Composer\Composer;
use Composer\Util\Filesystem;
use Composer\Factory as ComposerFactory;
use Horde\Composer\IOAdapter\FlowIoInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Horde\Composer\IOAdapter\SymphonyOutputAdapter;
use RuntimeException;
use strncasecmp;

class HordeReconfigureFlow
{
    private FlowIoInterface $io;
    /**
     * Modes: symlink, copy
     */
    private DirectoryTree $tree;

    public function __construct(DirectoryTree $tree, FlowIoInterface $io, public readonly ReconfigureOptions $options = new ReconfigureOptions())
    {
        $this->io = $io;
        $this->tree = $tree;
    }

    /**
     * Named Constructor.
     *
     * @param Composer $composer
     * @param FlowIoInterface|null $output
     * @return self
     */
    public static function fromComposer(Composer $composer, ?FlowIoInterface $output = null, ReconfigureOptions $options = new ReconfigureOptions()): self
    {
        return self::fromAnyComposer($composer, $output, $options);
    }

    public static function fromPartialComposer(PartialComposer $composer, ?FlowIoInterface $output = null, ReconfigureOptions $options = new ReconfigureOptions()): self
    {
        return self::fromAnyComposer($composer, $output, $options);
    }

    /**
     * Actual implementation of fromComposer / fromPartialComposer named constructors
     *
     * Composer may provide a PartialCompoer or a Composer object.
     * Use fromComposer and fromPartialComposer frontends
     *
     * @param Composer|PartialComposer $composer  A (partial) composer instance
     * @param FlowIoInterface|null $output An IO interface
     *
     * @TODO Refactor this once we require PHP 8.0 or higher
     */
    private static function fromAnyComposer($composer, ?FlowIoInterface $output = null, ReconfigureOptions $options = new ReconfigureOptions()): self
    {
        $mode = $options->mode;
        // Symlink mode does not work on Windows
        if ($mode == 'symlink') {
            $mode = strncasecmp(\PHP_OS, 'WIN', 3) === 0 ? 'copy' : 'symlink';
        }
        $tree = DirectoryTree::fromComposerJsonPath(ComposerFactory::getComposerFile());
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (!is_string($vendorDir)) {
            throw new RuntimeException('Cannot get vendor dir from config');
        }
        $outputInterface = $output ?? new SymphonyOutputAdapter(ComposerFactory::createOutput());
        $tree->withVendorDir($vendorDir);
        $flow = new HordeReconfigureFlow($tree, $outputInterface, $options);
        return $flow;
    }
    /**
     * Run the reconfigure flow
     */
    public function run(): int
    {
        // Get installed packages of types handled by installer
        $mode = $this->options->mode;
        $filesystem = new Filesystem();
        // This is sufficient for now but we actually know better
        $hordeApps = InstalledVersions::getInstalledPackagesByType('horde-application');
        $hordeLibraries = InstalledVersions::getInstalledPackagesByType('horde-library');
        $hordeThemes = InstalledVersions::getInstalledPackagesByType('horde-theme');

        // We could simply ask InstalledVersions here, too
        $rootPackageDir = $this->tree->getRootPackageDir();
        $vendorDir = $this->tree->getVendorDir();
        if ($this->options->force) {
            $this->io->writeln('Force mode enabled, removing existing files');
            // Todo: Delegate to a method or helper class
            foreach ($hordeApps as $app) {
                [$vendorName, $appName] = explode('/', $app);
                // horde.local.php files
                $filesystem->remove($this->tree->getVarConfigDir() . '/' . $appName . '/horde.local.php');
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/horde.local.php');
                if ($app == 'horde') {
                    // remove horde registry file
                    $filesystem->remove($this->tree->getVarConfigDir() . '/horde/registry.d/00-horde.php');
                    $filesystem->remove($this->tree->getVarConfigDir() . '/horde/registry.d/01-location-' . $appName . '.php');
                } else {
                    // remove app registry file
                    $filesystem->remove($this->tree->getVarConfigDir() . '/horde/registry.d/02-location-' . $appName . '.php');
                }
                // remove webdir items
                $filesystem->remove($this->tree->getWebReadableRootDir() . '/' . $appName);
                $filesystem->remove($this->tree->getWebReadableRootDir() . '/js/' . $appName);
                $filesystem->remove($this->tree->getWebReadableRootDir() . '/themes/' . $appName);
                // remove vendor dir items
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/conf.php');
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/hooks.php');
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/backends.local.php');
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/prefs.local.php');
                $filesystem->remove($vendorDir . '/' . $vendorName . '/' . $appName . '/config/routes.local.php');
            }
        } else {
            $this->io->writeln('Force mode not enabled, skipping removal of existing files');
        }
        $this->io->writeln('Applying /presets for absent files in /var/config');
        $presetHandler = new PresetHandler($rootPackageDir, $filesystem);
        $presetHandler->handle();
        $this->io->writeln('Distributing config .dist files from vendor to /var/config');
        $configDistributor = new ConfigDistributor(
            $rootPackageDir,
            $vendorDir,
            $filesystem,
            $this->io,
        );
        $configDistributor->run($hordeApps);
        $this->io->writeln('Looking for registry snippets from apps');
        $snippetHandler = new PackageDocRegistrySnippetHandler(
            $this->tree,
            $filesystem,
        );
        $snippetHandler->handle();
        $this->io->writeln('Configuration mode: ' . $mode);
        $this->io->writeln('Writing app configs to /var/config dir');
        $registrySnippetFileWriter = new RegistrySnippetFileWriter(
            $filesystem,
            $rootPackageDir,
            $hordeApps,
            $this->options,
        );
        $registrySnippetFileWriter->run();
        $hordeLocalWriter = new HordeLocalFileWriter(
            $filesystem,
            $rootPackageDir,
            $hordeApps,
            $mode,
        );
        $hordeLocalWriter->run();
        $this->io->writeln('Linking app configs to /vendor/horde/ Dir');
        $configLinker = new ConfigLinker($rootPackageDir, $mode, $this->io);
        $configLinker->run();
        $this->io->writeln('Linking javascript tree to /web/js');
        $jsLinker = new JsTreeLinker(
            $filesystem,
            $this->tree,
            $hordeApps,
            $hordeLibraries,
            $mode,
        );
        $jsLinker->run();
        $this->io->writeln('Linking vendor assets to /web/js');
        $vendorAssetLinker = new VendorAssetLinker(
            $filesystem,
            $vendorDir,
            $this->tree->getWebReadableRootDir(),
            array_merge($hordeApps, $hordeLibraries),
            $mode,
        );
        $vendorAssetLinker->run();
        $this->io->writeln('Linking themes tree to /web/themes');
        $themesHandler = new ThemesHandler(
            $filesystem,
            $rootPackageDir,
            $vendorDir,
            $mode,
        );

        foreach ($hordeThemes as $theme) {
            // register
            [$vendorName, $packageName] = explode('/', $theme);
            $themesHandler->themesCatalog->register(
                $vendorName,
                $packageName,
                $vendorDir . '/' . $theme,
            );
        }
        $themesHandler->setupThemes();
        // ApplicationLinker must run after all changes to /vendor
        $appLinker = new ApplicationLinker($filesystem, $hordeApps, $rootPackageDir, $mode);
        $appLinker->run();
        // Clean up obsolete PHP files from web/ dirs (after routing migrations)
        $this->io->writeln('Cleaning up obsolete web files');
        $webFilesCleaner = new ObsoleteWebFilesCleaner($hordeApps, $rootPackageDir, $this->io);
        $webFilesCleaner->run();
        // Clean up dead symlinks in web/js/ and web/themes/
        $this->io->writeln('Cleaning up dead symlinks in web/js/ and web/themes/');
        $symlinkCleaner = new DeadSymlinkCleaner($rootPackageDir, $this->io);
        $symlinkCleaner->run();
        return 0;
    }
}
