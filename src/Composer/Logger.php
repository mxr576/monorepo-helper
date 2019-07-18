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

use Composer\IO\IOInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger wrapper around IOInterface.
 */
final class Logger extends AbstractLogger
{
    /**
     * @var \Composer\IO\IOInterface
     */
    private $io;

    /**
     * Logger constructor.
     *
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = []): void
    {
        switch ($level) {
            case LogLevel::ERROR:
            case LogLevel::CRITICAL:
            case LogLevel::ALERT:
            case LogLevel::EMERGENCY:
                $this->io->writeError([$this->buildMessage($message, $context)]);
                break;
            case LogLevel::DEBUG:
                if ($this->io->isDebug()) {
                    $this->io->write([$this->buildMessage($message, $context)]);
                }
                break;
            default:
                if ($this->io->isVerbose()) {
                    $this->io->write([$this->buildMessage($message, $context)]);
                }
        }
    }

    /**
     * Interpolates context values into the message placeholders and prefixes the message with the plugin's name.
     *
     * @param string $message
     * @param array $context
     *
     * @return string
     *
     * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message
     */
    private function buildMessage(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // Check that the value can be casted to string.
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return 'Monorepo Helper: ' . strtr($message, $replace);
    }
}
