<?php

declare(strict_types=1);

/**
 * This file is part of the Mailer for AbraFlexi package
 *
 * https://github.com/VitexSoftware/abraflexi-mailer
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Mailer;

/**
 * Configuration helpers.
 */
class Config
{
    /**
     * Read a yes/no configuration value.
     *
     * Environment values are strings, so "false" would be truthy in PHP.
     * Only "true", "1", "yes" and "on" (case insensitive) mean true.
     */
    public static function bool(string $name, bool $default = false): bool
    {
        $value = \Ease\Shared::cfg($name);

        if ($value === null || $value === '') {
            return $default;
        }

        if (\is_bool($value)) {
            return $value;
        }

        return \in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
