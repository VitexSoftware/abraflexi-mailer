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

use AbraFlexi\Mailer\PaymentReceipt;
use PHPUnit\Framework\TestCase;

/**
 * Which payments are confirmed to the customer, and the mark of a confirmed one.
 */
class PaymentReceiptTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function payment(array $override = []): array
    {
        return array_merge([
            'typPohybuK' => 'typPohybu.prijem',
            'firma' => 'code:POKUS',
            'storno' => 'false',
            'stitky' => '',
            'sumCelkem' => '32.0',
        ], $override);
    }

    public function testIncomingPaymentOfKnownPayerIsConfirmed(): void
    {
        $this->assertNull(PaymentReceipt::skipReason(self::payment()));
    }

    public function testUnknownPayerWaitsUntilHeIsKnown(): void
    {
        $this->assertSame('payer unknown', PaymentReceipt::skipReason(self::payment(['firma' => ''])));
        $this->assertSame('payer unknown', PaymentReceipt::skipReason(self::payment(['firma' => 'code:'])));
        $this->assertSame('payer unknown', PaymentReceipt::skipReason(self::payment(['firma' => null])));
    }

    public function testOutgoingPaymentIsNotConfirmed(): void
    {
        $this->assertSame('not an incoming payment', PaymentReceipt::skipReason(self::payment(['typPohybuK' => 'typPohybu.vydej'])));
    }

    public function testCancelledAndEmptyPaymentIsNotConfirmed(): void
    {
        $this->assertSame('cancelled', PaymentReceipt::skipReason(self::payment(['storno' => true])));
        $this->assertSame('cancelled', PaymentReceipt::skipReason(self::payment(['storno' => 'true'])));
        $this->assertSame('no amount', PaymentReceipt::skipReason(self::payment(['sumCelkem' => '0.0'])));
    }

    public function testConfirmedPaymentIsNotConfirmedAgain(): void
    {
        $this->assertSame('already confirmed', PaymentReceipt::skipReason(self::payment(['stitky' => 'API, UHRADA_POTVRZENA'])));
        $this->assertSame('already confirmed', PaymentReceipt::skipReason(self::payment(['stitky' => 'code:UHRADA_POTVRZENA'])));
    }

    public function testRelationOfTheLibraryIsAccepted(): void
    {
        $this->assertSame('POKUS', PaymentReceipt::company(['value' => 'code:POKUS']));
        $this->assertSame('POKUS', PaymentReceipt::company('code:POKUS'));
        $this->assertSame('', PaymentReceipt::company(null));
    }

    public function testMarkIsAddedOnceAndKeepsOtherLabels(): void
    {
        $this->assertSame('UHRADA_POTVRZENA', PaymentReceipt::withLabel(''));
        $this->assertSame('API, SYSTEM, UHRADA_POTVRZENA', PaymentReceipt::withLabel('API, SYSTEM'));
        $this->assertSame('API, UHRADA_POTVRZENA', PaymentReceipt::withLabel('code:API,UHRADA_POTVRZENA'));
        $this->assertSame('API', PaymentReceipt::withoutLabel('API, UHRADA_POTVRZENA'));
        $this->assertSame('', PaymentReceipt::withoutLabel(null));
    }

    public function testOnlyEvidencesOfReceivedMoney(): void
    {
        $this->assertSame('banka', PaymentReceipt::evidence('banka'));
        $this->assertSame('pokladni-pohyb', PaymentReceipt::evidence(' pokladni-pohyb '));
        $this->assertNull(PaymentReceipt::evidence('faktura-vydana'));
        $this->assertNull(PaymentReceipt::evidence('banka/../adresar'));
        $this->assertNull(PaymentReceipt::evidence(null));
    }

    public function testInvoicesOfLinks(): void
    {
        $links = [
            ['a@ref' => '/c/x/faktura-vydana/2243.json', 'a@showAs' => 'VF2-0001/2026', 'b@ref' => '/c/x/banka/7619.json', 'b@showAs' => 'FIO0+0074/26'],
            ['a@ref' => '/c/x/faktura-vydana-polozka/7674.json', 'a@showAs' => 'VF1-0071/2026 - pol. č. 2', 'b@ref' => '/c/x/faktura-vydana/2243.json', 'b@showAs' => 'VF2-0001/2026'],
        ];
        $this->assertSame(['VF2-0001/2026'], PaymentReceipt::invoices($links));
        $this->assertSame([], PaymentReceipt::invoices([]));
    }
}
