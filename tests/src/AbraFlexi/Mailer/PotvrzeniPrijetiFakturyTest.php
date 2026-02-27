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

use AbraFlexi\Mailer\PotvrzeniPrijetiFaktury;

/**
 * Test for PotvrzeniPrijetiFaktury.
 */
class PotvrzeniPrijetiFakturyTest extends \PHPUnit\Framework\TestCase
{
    protected ?PotvrzeniPrijetiFaktury $object = null;

    /**
     * Sets up the fixture.
     */
    protected function setUp(): void
    {
        $invoicer = new \AbraFlexi\FakturaPrijata();
        $firstId = $invoicer->getFirstRecordID();

        if ($firstId) {
            $invoicer->loadFromAbraFlexi($firstId);
            $this->object = new PotvrzeniPrijetiFaktury($invoicer);
        } else {
            $this->markTestSkipped('No received invoice found in AbraFlexi');
        }
    }

    /**
     * @covers \AbraFlexi\Mailer\PotvrzeniPrijetiFaktury::__construct
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(PotvrzeniPrijetiFaktury::class, $this->object);
    }
}
