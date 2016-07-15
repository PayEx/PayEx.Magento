<?php

class PayEx_Payments_Test_Helper_Tools extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = Mage::helper('payex/tools');
    }

    /**
     * Check Methods
     */
    public function testMethods()
    {
        $methods = array(
            'throwPayExException', 'debugApi', 'addToDebug', 'getErrorMessageByCode', 'getVerboseErrorMessage',
            'getNameParser'
        );
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->object, $method), 'Class does not have method ' . $method);
        }
    }

    /**
     * Test Logging
     */
    public function testLogging()
    {
        $log_file = Mage::getBaseDir('var') . DS . 'log' . DS . 'payment_payex.log';
        if (file_exists($log_file)) {
            unlink($log_file);
        }
        $this->object->addToDebug('Log test');
        //$this->assertFileExists($log_file);
        //$this->assertContains('Log test', file_get_contents($log_file));
    }

    /**
     * Check NameParser
     */
    public function testNameParser()
    {
        $nameparser = $this->object->getNameParser();
        $this->assertEquals('FullNameParser', get_class($nameparser));
    }

    /**
     * tearDown
     */
    protected function tearDown()
    {
        unset($this->object);
        parent::tearDown();
    }
}
