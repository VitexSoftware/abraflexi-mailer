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
 * Language of the customer a document is mailed to.
 *
 * ClientZone remembers the language the customer chose as the label LANG_xx
 * ("LANG_CS", "LANG_EN", ...) of the customer's company (adresar) in AbraFlexi.
 * A mail to the customer is written in that language; without the label the
 * configured language (LOCALIZE) is used.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class CustomerLanguage
{
    public const LABEL_PREFIX = 'LANG_';

    /**
     * Locale used for a language the mailer ships translations for.
     */
    public const LOCALES = [
        'cs' => 'cs_CZ',
        'en' => 'en_US',
    ];

    /**
     * Two letter language code found among the labels of a company.
     *
     * @param array<int, string>|string|null $labels labels as a list, or as the comma separated text AbraFlexi sends
     *
     * @return null|string lower case language code, null when there is no language label
     */
    public static function fromLabels(array|string|null $labels): ?string
    {
        $codes = \is_array($labels) ? $labels : explode(',', (string) $labels);

        foreach ($codes as $code) {
            $code = strtoupper(trim((string) preg_replace('/^code:/i', '', trim((string) $code))));

            if (preg_match('/^'.self::LABEL_PREFIX.'([A-Z]{2})$/', $code, $matches) === 1) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    /**
     * Two letter language code of a locale: "cs_CZ" gives "cs".
     */
    public static function fromLocale(?string $locale): ?string
    {
        if (preg_match('/^([a-z]{2})(?:[_-][A-Za-z]{2})?(?:[.@].*)?$/i', trim((string) $locale), $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Locale to write a mail in.
     *
     * @param null|string $language language of the customer (null when unknown)
     * @param string      $default  locale used for an unknown language or one without translation
     *
     * @return string locale such as "cs_CZ"
     */
    public static function localeFor(?string $language, string $default): string
    {
        return self::LOCALES[(string) $language] ?? $default;
    }

    /**
     * Language of the company the document belongs to.
     *
     * AbraFlexi failing or a company without the label is not an error: the caller falls back.
     *
     * @return null|string language code, null when unknown
     */
    public static function forDocument(\AbraFlexi\RO $document): ?string
    {
        $firma = $document->getDataValue('firma');
        $firma = \is_object($firma) ? (string) $firma : (string) (\is_array($firma) ? ($firma['value'] ?? '') : $firma);

        if ($firma === '') {
            return null;
        }

        try {
            $adresar = new \AbraFlexi\Adresar(null, ['ignore404' => true]);
            $adresar->throwException = false;
            $rows = $adresar->getColumnsFromAbraFlexi(['id', 'stitky'], ['id' => $firma]);
        } catch (\Throwable) {
            return null;
        }

        $row = \is_array($rows) ? (array) ($rows[0] ?? []) : [];

        return self::fromLabels($row['stitky'] ?? null);
    }
}
