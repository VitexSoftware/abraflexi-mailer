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

use AbraFlexi\Mailer\PotvrzeniPrijetiUhrady;

/**
 * Test for PotvrzeniPrijetiUhrady.
 */
class PotvrzeniPrijetiUhradyTest extends \PHPUnit\Framework\TestCase
{
    protected ?PotvrzeniPrijetiUhrady $object = null;

    /**
     * Sets up the fixture.
     */
    protected function setUp(): void
    {
        $invoicer = new \AbraFlexi\FakturaVydana();
        $firstId = $invoicer->getFirstRecordID();

        if ($firstId) {
            $invoicer->loadFromAbraFlexi($firstId);
            $this->object = new PotvrzeniPrijetiUhrady($invoicer);
        } else {
            $this->markTestSkipped('No invoice found in AbraFlexi');
        }
    }

    /**
     * @covers \AbraFlexi\Mailer\PotvrzeniPrijetiUhrady::__construct
     */
    public function testConstructor(): void
    {
        $this->assertInstanceOf(PotvrzeniPrijetiUhrady::class, $this->object);
    }
}
