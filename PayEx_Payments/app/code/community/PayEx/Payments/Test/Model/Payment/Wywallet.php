<?php

class PayEx_Payments_Test_Model_Payment_Wywallet extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * SetUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = Mage::getModel('payex/payment_wywallet');
    }


    /**
     * Check Attributes
     */
    public function testAttributes()
    {
        $this->assertAttributeEquals('payex_wywallet', '_code', $this->object);
        $this->assertAttributeEquals('payex/form_wywallet', '_formBlockType', $this->object);
        $this->assertAttributeEquals('payex/info_wywallet', '_infoBlockType', $this->object);
        $this->assertAttributeEquals(true, '_isGateway', $this->object);
        $this->assertAttributeEquals(true, '_canAuthorize', $this->object);
        $this->assertAttributeEquals(true, '_canCapture', $this->object);
        $this->assertAttributeEquals(false, '_canCapturePartial', $this->object);
        $this->assertAttributeEquals(true, '_canRefund', $this->object);
        $this->assertAttributeEquals(false, '_canRefundInvoicePartial', $this->object);
        $this->assertAttributeEquals(true, '_canVoid', $this->object);
        $this->assertAttributeEquals(true, '_canUseInternal', $this->object);
        $this->assertAttributeEquals(true, '_canUseCheckout', $this->object);
        $this->assertAttributeEquals(false, '_canUseForMultishipping', $this->object);
        $this->assertAttributeEquals(true, '_canFetchTransactionInfo', $this->object);
    }

    /**
     * Check Methods
     */
    public function testMethods()
    {
        $methods = array(
            'isAvailable', 'validate', 'getOrderPlaceRedirectUrl', 'capture', 'cancel', 'refund', 'void',
            'fetchTransactionInfo'
        );
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->object, $method), 'Class does not have method ' . $method);
        }
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
