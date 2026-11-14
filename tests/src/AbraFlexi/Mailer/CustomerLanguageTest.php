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

namespace Test\AbraFlexi\Mailer;

use AbraFlexi\Mailer\CustomerLanguage;
use PHPUnit\Framework\TestCase;

/**
 * The language of the customer decides the language of the mail.
 */
class CustomerLanguageTest extends TestCase
{
    public function testLanguageIsFoundAmongLabels(): void
    {
        $this->assertSame('cs', CustomerLanguage::fromLabels(['ESHOP', 'LANG_CS']));
        $this->assertSame('en', CustomerLanguage::fromLabels('ESHOP, code:LANG_EN, VIP'));
        $this->assertNull(CustomerLanguage::fromLabels(['ESHOP', 'VIP']));
        $this->assertNull(CustomerLanguage::fromLabels(null));
        $this->assertNull(CustomerLanguage::fromLabels('LANGUAGE_CS'));
    }

    public function testLanguageOfLocale(): void
    {
        $this->assertSame('cs', CustomerLanguage::fromLocale('cs_CZ'));
        $this->assertSame('en', CustomerLanguage::fromLocale('en_US.UTF-8'));
        $this->assertNull(CustomerLanguage::fromLocale(''));
        $this->assertNull(CustomerLanguage::fromLocale(null));
    }

    public function testLocaleForLanguageWithTranslationAndFallback(): void
    {
        $this->assertSame('en_US', CustomerLanguage::localeFor('en', 'cs_CZ'));
        $this->assertSame('cs_CZ', CustomerLanguage::localeFor('cs', 'en_US'));
        // no translation for German: the configured locale is kept
        $this->assertSame('cs_CZ', CustomerLanguage::localeFor('de', 'cs_CZ'));
        $this->assertSame('cs_CZ', CustomerLanguage::localeFor(null, 'cs_CZ'));
    }
}
