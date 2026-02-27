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

use AbraFlexi\Mailer\PotvrzeniOdeslaniUhrady;

/**
 * Test for PotvrzeniOdeslaniUhrady.
 */
class PotvrzeniOdeslaniUhradyTest extends \PHPUnit\Framework\TestCase
{
    protected ?PotvrzeniOdeslaniUhrady $object = null;

    /**
     * Sets up the fixture.
     */
    protected function setUp(): void
    {
        $invoicer = new \AbraFlexi\FakturaPrijata();
        $firstId = $invoicer->getFirstRecordID();

        if ($firstId) {
            $invoicer->loadFromAbraFlexi($firstId);
            $this->object = new PotvrzeniOdeslaniUhrady($invoicer);
        } else {
            $this->markTestSkipped('No received invoice found in AbraFlexi');
        }
    }

    /**
     * @covers \AbraFlexi\Mailer\PotvrzeniOdeslaniUhrady::__construct
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(PotvrzeniOdeslaniUhrady::class, $this->object);
    }
}
