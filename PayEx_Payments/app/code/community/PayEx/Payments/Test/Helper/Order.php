<?php

class PayEx_Payments_Test_Helper_Order extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = Mage::helper('payex/order');
    }

    /**
     * Check Methods
     */
    public function testMethods()
    {
        $methods = array(
            'processPaymentTransaction', 'makeInvoice', 'makeCreditMemo', 'getCalculatedOrderAmount', 'addOrderLine',
            'addOrderAddress', 'getShoppingCartXML', 'getInvoiceExtraPrintBlocksXML', 'getAssignedStatus',
            'getInvoiceLink'
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
