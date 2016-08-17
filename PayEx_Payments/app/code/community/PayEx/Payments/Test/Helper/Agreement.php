<?php

class PayEx_Payments_Test_Helper_Agreement extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = Mage::helper('payex/agreement');
    }

    /**
     * Check Methods
     */
    public function testMethods()
    {
        $methods = array(
            'getCustomerAgreement', 'getPxAgreementStatus'
        );
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->object, $method), 'Class does not have method ' . $method);
        }
    }
}
