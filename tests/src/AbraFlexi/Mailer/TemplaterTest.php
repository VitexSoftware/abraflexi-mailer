<?php

namespace Test\AbraFlexi\Mailer;

use AbraFlexi\Mailer\Templater;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2022-10-14 at 14:18:43.
 */
class TemplaterTest extends \PHPUnit\Framework\TestCase {

    /**
     * @var Templater
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void {
        $this->object = new Templater();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void {
        
    }

    /**
     * @covers AbraFlexi\Mailer\Templater::process
     * @todo   Implement testprocess().
     */
    public function testprocess() {
        $this->assertEquals('', $this->object->process());
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @covers AbraFlexi\Mailer\Templater::variableBase
     * @todo   Implement testvariableBase().
     */
    public function testvariableBase() {
        $this->assertEquals('', $this->object->variableBase());
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @covers AbraFlexi\Mailer\Templater::variableProperty
     * @todo   Implement testvariableProperty().
     */
    public function testvariableProperty() {
        $this->assertEquals('', $this->object->variableProperty());
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

    /**
     * @covers AbraFlexi\Mailer\Templater::stripMarkup
     * @todo   Implement teststripMarkup().
     */
    public function teststripMarkup() {
        $this->assertEquals('', $this->object->stripMarkup());
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete('This test has not been implemented yet.');
    }

}
