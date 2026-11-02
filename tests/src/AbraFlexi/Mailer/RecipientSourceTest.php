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

use AbraFlexi\Mailer\RecipientSource;
use PHPUnit\Framework\TestCase;

class RecipientSourceTest extends TestCase
{
    public function testDefaultEvidenceIsSupported(): void
    {
        $this->assertTrue(RecipientSource::isSupported(RecipientSource::DEFAULT_EVIDENCE));
        $this->assertSame('email', RecipientSource::getEmailColumn('adresar'));
    }

    public function testDocumentsUseKontaktEmail(): void
    {
        $this->assertSame('kontaktEmail', RecipientSource::getEmailColumn('faktura-vydana'));
    }

    public function testUnsupportedEvidence(): void
    {
        $this->assertFalse(RecipientSource::isSupported('nastaveni'));
        $this->expectException(\InvalidArgumentException::class);
        RecipientSource::getEmailColumn('nastaveni');
    }

    public function testAppJsonOptionsMatch(): void
    {
        $app = json_decode(file_get_contents(__DIR__.'/../../../../multiflexi/abraflexi_bulkmail.multiflexi.app.json'), true);
        $env = $app['environment']['ABRAFLEXI_BULKMAIL_EVIDENCE'];
        $this->assertSame('adresar', $env['defval']);
        $this->assertEqualsCanonicalizing(array_keys(RecipientSource::EVIDENCES), $env['options']);
    }
}
