<?php

class PayEx_Payments_Test_Block_Info_Wywallet extends EcomDev_PHPUnit_Test_Case
{
    var $object;

    /**
     * setUp
     */
    public function setUp()
    {
        parent::setUp();
        $this->object = self::app()->getLayout()->createBlock('payex/info_wywallet');
    }

    /**
     * Test Block
     */
    public function testInfoBlock()
    {
        $this->assertAttributeEquals('payex/wywallet/info.phtml', '_template', $this->object);
    }

    /**
     * tearDown
     */
    public function tearDown()
    {
        unset($this->object);
        parent::tearDown();
    }
}
