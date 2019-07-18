<?php

declare(strict_types=1);

/**
 * Copyright (C) 2019 PRONOVIX GROUP BVBA.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *  *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *  *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301,
 * USA.
 */

namespace Pronovix\MonorepoHelper\Composer;

use Composer\Json\JsonFile;
use Composer\Package\Loader\LoaderInterface;
use Composer\Repository\ArrayRepository;
use Composer\Util\ProcessExecutor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

/**
 * Repository plugin that discovers packages inside a monorepo.
 */
final class MonorepoRepository extends ArrayRepository
{
    /**
     * Absolute root path of the monorepo.
     *
     * @var string
     */
    private $monorepoRoot;

    /**
     * @var \Composer\Util\ProcessExecutor
     */
    private $process;

    /**
     * @var \Composer\Package\Loader\LoaderInterface
     */
    private $loader;

    /**
     * @var bool
     */
    private $enabled = true;

    /**
     * @var \Pronovix\MonorepoHelper\Composer\Logger|\Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Pronovix\MonorepoHelper\Composer\PluginConfiguration
     */
    private $configuration;
    /**
     * @var \Pronovix\MonorepoHelper\Composer\MonorepoVersionGuesser
     */
    private $monorepoVersionGuesser;

    /**
     * MonorepoRepository constructor.
     *
     * @param string $monorepoRoot
     * @param \Pronovix\MonorepoHelper\Composer\PluginConfiguration $configuration
     * @param \Composer\Package\Loader\LoaderInterface $loader
     * @param \Composer\Util\ProcessExecutor $process
     * @param \Pronovix\MonorepoHelper\Composer\MonorepoVersionGuesser $monorepoVersionGuesser
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(string $monorepoRoot, PluginConfiguration $configuration, LoaderInterface $loader, ProcessExecutor $process, MonorepoVersionGuesser $monorepoVersionGuesser, LoggerInterface $logger)
    {
        $this->monorepoRoot = $monorepoRoot;
        $this->monorepoVersionGuesser = $monorepoVersionGuesser;
        $this->configuration = $configuration;
        $this->loader = $loader;
        $this->process = $process;
        $this->logger = $logger;

        parent::__construct();
    }

    /**
     * Disables the repository handler.
     *
     * @param string $reason
     *   Explanation why the repository handler got disabled.
     */
    public function disable(string $reason): void
    {
        $this->enabled = false;
        $this->logger->info($reason);
    }

    /**
     * @inheritDoc
     *
     * @see \Composer\Repository\PathRepository::initialize()
     */
    protected function initialize(): void
    {
        parent::initialize();

        if ($this->enabled) {
            foreach ($this->getPackageRoots() as $packageRoot) {
                $composerFilePath = $packageRoot . DIRECTORY_SEPARATOR . 'composer.json';

                $json = file_get_contents($composerFilePath);
                $package_data = JsonFile::parseJson($json, $composerFilePath);
                $package_data['dist'] = [
                    'type' => 'path',
                    'url' => $packageRoot,
                    'reference' => sha1($json),
                ];

                // Enforce symlinking instead of copying.
                $package_data['transport-options'] = ['symlink' => true];
                $package_data['version'] = $this->monorepoVersionGuesser->getPackageVersion($package_data, $packageRoot);

                $output = '';
                if (is_dir($this->monorepoRoot . DIRECTORY_SEPARATOR . '.git') && 0 === $this->process->execute('git log -n1 --pretty=%H', $output, $packageRoot)) {
                    $package_data['dist']['reference'] = trim($output);
                }
                /** @var \Composer\Package\Package $package */
                $package = $this->loader->load($package_data);
                $this->addPackage($package);
                $this->logger->info('Added {package} {type} as {version} version from the monorepo.', ['package' => $package->getPrettyName(), 'type' => $package->getType(), 'version' => $package->getPrettyVersion()]);
            }
        }
    }

    /**
     * Get a list of all subpackage directories.
     *
     * @return \Generator
     *   Array of subpackage directories.
     */
    private function getPackageRoots(): \Generator
    {
        $finder = new Finder();
        $projects = $finder
            ->in($this->monorepoRoot)
            ->depth("<= {$this->configuration->getMaxDiscoveryDepth()}")
            ->notPath('vendor')
            ->files()->name('composer.json');

        foreach ($projects as $project) {
            /* @var $project \SplFileInfo */
            yield $project->getPath();
        }
    }
}
