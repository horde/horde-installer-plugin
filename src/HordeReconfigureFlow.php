<?php

/**
 * Factor out the common workflow from the installer plugin and the reconfigure command
 * This simplifies code reuse
 */

declare(strict_types=1);

namespace Horde\Composer;

use Composer\InstalledVersions;
use Composer\PartialComposer;
use Composer\Util\Filesystem;
use Composer\Factory as ComposerFactory;
use Horde\Composer\IOAdapter\FlowIoInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Horde\Composer\IOAdapter\SymphonyOutputAdapter;
use RuntimeException;


class HordeReconfigureFlow
{
    private FlowIoInterface $io;
    /**
     * Modes: symlink, copy
     */
    private string $mode = 'symlink';
    private DirectoryTree $tree;

    public function __construct(DirectoryTree $tree, FlowIoInterface $io, string $mode = 'symlink')
    {
        $this->io = $io;
        $this->mode = $mode;
        $this->tree = $tree;
    }

    public static function fromComposer(PartialComposer $composer, ?FlowIoInterface $output = null): self
    {
        $mode = \strncasecmp(\PHP_OS, 'WIN', 3) === 0 ? 'copy' : 'symlink';
        $tree = DirectoryTree::fromComposerJsonPath(ComposerFactory::getComposerFile());
        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (!is_string($vendorDir)) {
            throw new RuntimeException('Cannot get vendor dir from config');
        }
        $outputInterface = $output ?? new SymphonyOutputAdapter(ComposerFactory::createOutput());
        $tree->withVendorDir($vendorDir);
        $flow = new HordeReconfigureFlow($tree, $outputInterface, $mode);
        return $flow;
    }
    /**
     * Run the reconfigure flow
     */
    public function run(): int
    {
        // Get installed packages of types handled by installer
        $filesystem = new Filesystem();
        // This is sufficient for now but we actually know better
        $hordeApps = InstalledVersions::getInstalledPackagesByType('horde-application');
        $hordeLibraries = InstalledVersions::getInstalledPackagesByType('horde-library');
        $hordeThemes = InstalledVersions::getInstalledPackagesByType('horde-theme');

        // We could simply ask InstalledVersions here, too
        $rootPackageDir = $this->tree->getRootPackageDir();
        $vendorDir = $this->tree->getVendorDir();
        $this->io->writeln('Applying /presets for absent files in /var/config');
        $presetHandler = new PresetHandler($rootPackageDir, $filesystem);
        $presetHandler->handle();
        $this->io->writeln('Looking for registry snippets from apps');
        $snippetHandler = new PackageDocRegistrySnippetHandler(
            $this->tree,
            $filesystem
        );
        $snippetHandler->handle();

        $this->io->writeln('Writing app configs to /var/config dir');
           $registrySnippetFileWriter = new RegistrySnippetFileWriter(
            $filesystem,
            $rootPackageDir,
            $hordeApps
        );
        $registrySnippetFileWriter->run();
        $hordeLocalWriter = new HordeLocalFileWriter(
            $filesystem,
            $rootPackageDir,
            $hordeApps,
        );
        $hordeLocalWriter->run();
        $this->io->writeln('Linking app configs to /web Dir');
        $configLinker = new ConfigLinker($rootPackageDir, $this->mode);
        $configLinker->run();
        $this->io->writeln('Linking javascript tree to /web/js');
        $jsLinker = new JsTreeLinker(
            $filesystem,
            $rootPackageDir,
            $hordeApps,
            $hordeLibraries,
            $this->mode
        );
        $jsLinker->run();
        $this->io->writeln('Linking themes tree to /web/themes');
        $themesHandler = new ThemesHandler(
            $filesystem,
            $rootPackageDir,
            $vendorDir,
            $this->mode
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
        $appLinker = new ApplicationLinker($filesystem, $hordeApps, $rootPackageDir, $this->mode);
        $appLinker->run();
        return 0;
    }
}
