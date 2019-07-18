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

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\Version\VersionGuesser;
use Composer\Package\Version\VersionParser;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Util\ProcessExecutor;

/**
 * Monorepo Helper plugin definition.
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var \Pronovix\MonorepoHelper\Composer\MonorepoRepository|null
     */
    private $repository;

    /** @var \Pronovix\MonorepoHelper\Composer\Logger */
    private $logger;

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->logger = new Logger($io);
        $process = new ProcessExecutor($io);
        $monorepoRoot = null;
        $output = '';
        if (0 === $process->execute('git rev-parse --absolute-git-dir', $output)) {
            $monorepoRoot = dirname(trim($output));
        }

        if (null === $monorepoRoot) {
            $this->logger->info('Plugin is disabled because no GIT root found in {dir} directory', ['dir' => realpath(getcwd())]);

            return;
        }

        $configuration = new PluginConfiguration($composer);
        $versionParser = new VersionParser();
        $monorepoVersionGuesser = new MonorepoVersionGuesser($monorepoRoot, new VersionGuesser($composer->getConfig(), $process, $versionParser), $process, $configuration, $this->logger);
        $this->repository = new MonorepoRepository($monorepoRoot, $configuration, new ArrayLoader($versionParser, true), $process, $monorepoVersionGuesser, $this->logger);
        // This ensures that the monorepo repository provides trumps both Packagist and Drupal packagist, so even if
        // the same version is available in multiple repositories the monorepo versions wins. Well, this is not entirely
        // true, it wins for dev versions but for >= alpha versions a different rule applies. See more details in
        // \Pronovix\MonorepoHelper\Composer\MonorepoVersionGuesser.
        $composer->getRepositoryManager()->prependRepository($this->repository);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::COMMAND => [
                ['onCommand', 0],
            ],
        ];
    }

    /**
     * Reacts to Composer commands.
     *
     * @param \Composer\Plugin\CommandEvent $event
     */
    public function onCommand(CommandEvent $event): void
    {
        if (null === $this->repository) {
            return;
        }

        if (!function_exists('proc_open')) {
            $this->repository->disable('Plugin is disabled because "proc_open" function does not exist.');

            return;
        }
        if ($event->getInput()->hasOption('prefer-lowest') && $event->getInput()->getOption('prefer-lowest')) {
            $this->repository->disable('Plugin is disabled on prefer-lowest installs.');
        }
    }
}
