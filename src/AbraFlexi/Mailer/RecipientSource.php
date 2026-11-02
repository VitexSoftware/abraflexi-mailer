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
 * Evidences usable as a source of bulk mail recipients
 * and the column holding the e-mail address in each of them.
 */
class RecipientSource
{
    public const DEFAULT_EVIDENCE = 'adresar';

    /**
     * Evidence => column with the e-mail address.
     *
     * Keep in sync with "options" of ABRAFLEXI_BULKMAIL_EVIDENCE
     * in multiflexi/abraflexi_bulkmail.multiflexi.app.json
     *
     * @var array<string, string>
     */
    public const EVIDENCES = [
        'adresar' => 'email',
        'kontakt' => 'email',
        'osoba' => 'email',
        'misto-urceni' => 'email',
        'faktura-vydana' => 'kontaktEmail',
        'faktura-prijata' => 'kontaktEmail',
        'nabidka-vydana' => 'kontaktEmail',
        'nabidka-prijata' => 'kontaktEmail',
        'objednavka-vydana' => 'kontaktEmail',
        'objednavka-prijata' => 'kontaktEmail',
        'poptavka-vydana' => 'kontaktEmail',
        'poptavka-prijata' => 'kontaktEmail',
        'pohledavka' => 'kontaktEmail',
        'zavazek' => 'kontaktEmail',
        'prodejka' => 'kontaktEmail',
        'interni-doklad' => 'kontaktEmail',
        'pokladni-pohyb' => 'kontaktEmail',
        'banka' => 'kontaktEmail',
        'skladovy-pohyb' => 'kontaktEmail',
        'uplatneni-dane-zavazku' => 'kontaktEmail',
        'vzajemny-zapocet' => 'kontaktEmail',
        'doklad-k-uhrade' => 'kontaktEmail',
    ];

    public static function isSupported(string $evidence): bool
    {
        return \array_key_exists($evidence, self::EVIDENCES);
    }

    /**
     * Column holding the e-mail address for given evidence.
     *
     * @throws \InvalidArgumentException for unsupported evidence
     */
    public static function getEmailColumn(string $evidence): string
    {
        if (!self::isSupported($evidence)) {
            throw new \InvalidArgumentException($evidence);
        }

        return self::EVIDENCES[$evidence];
    }
}
