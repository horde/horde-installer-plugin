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
use Horde\Composer\IOAdapter\FlowIoInterface;
use RuntimeException;

class HordeReconfigureFlow
{
    private FlowIoInterface $io;
    private PartialComposer $composer;

    public function __construct(FlowIoInterface $io, PartialComposer $composer)
    {
        $this->io = $io;
        $this->composer = $composer;
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

        // TODO: Supply vendor dir everywhere. While it's a bad idea, it is supposed to work
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');

        if (!is_string($vendorDir)) {
            throw new RuntimeException('Cannot get vendor dir from config');
        }
        // We could simply ask InstalledVersions here, too
        $rootPackageDir = dirname($vendorDir);
        $this->io->writeln('Applying /presets for absent files in /var/config');
        $appLinker = new ApplicationLinker($filesystem, $hordeApps, $rootPackageDir, 'symlink');
        $appLinker->run();
        $presetHandler = new PresetHandler($rootPackageDir, $filesystem);
        $presetHandler->handle();
        $this->io->writeln('Looking for registry snippets from apps');
        $snippetHandler = new PackageDocRegistrySnippetHandler(
            $rootPackageDir,
            $filesystem,
            $hordeApps
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
            $hordeApps
        );
        $hordeLocalWriter->run();
        $this->io->writeln('Linking app configs to /web Dir');
        $configLinker = new ConfigLinker($rootPackageDir);
        $configLinker->run();
        $this->io->writeln('Linking javascript tree to /web/js');
        $jsLinker = new JsTreeLinker(
            $filesystem,
            $rootPackageDir,
            $hordeApps,
            $hordeLibraries
        );
        $jsLinker->run();
        $this->io->writeln('Linking themes tree to /web/themes');
        $themesHandler = new ThemesHandler($filesystem, $rootPackageDir);
        $themesHandler->setupThemes();
        return 0;
    }
}
