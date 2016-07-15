<?php

class PayEx_Payments_Test_Helper_Api extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = Mage::helper('payex/api');
    }

    /**
     * Check Px
     */
    public function testPx()
    {

        $px = $this->object->getPx();
        $this->assertEquals('PayEx\Px', get_class($px));
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
