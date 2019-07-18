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

/**
 * Value object that stores this plugin's configuration.
 */
final class PluginConfiguration
{
    /** @var int */
    private const DEFAULT_PACKAGE_DISCOVERY_DEPTH = 5;

    /** @var bool */
    private $offlineMode;

    /** @var int */
    private $maxDiscoveryDepth;

    /**
     * PluginConfiguration constructor.
     *
     * @param \Composer\Composer $composer
     */
    public function __construct(Composer $composer)
    {
        $extra = $composer->getPackage()->getExtra();
        $monorepo_helper = $extra['monorepo-helper'] ?? [];
        $this->offlineMode = (bool) ($monorepo_helper['offline-mode'] ?? getenv('PRONOVIX_MONOOREPO_HELPER_OFFLINE_MODE') ?? false);
        // 0 as max discovery depth is not valid.
        $this->maxDiscoveryDepth = (int) ($monorepo_helper['max-discover-depth'] ?? getenv('PRONOVIX_MONOREPO_HELPER_MAX_DISCOVERY_DEPTH')) ?: self::DEFAULT_PACKAGE_DISCOVERY_DEPTH;
    }

    /**
     * @return int
     */
    public function getMaxDiscoveryDepth(): int
    {
        return $this->maxDiscoveryDepth;
    }

    /**
     * @return bool
     */
    public function isOfflineMode(): bool
    {
        return $this->offlineMode;
    }
}
