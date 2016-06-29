<?php

class PayEx_Payments_Model_Source_DiscountCalculation
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'classic',
                'label' => Mage::helper('payex')->__('Classic')
            ),
            array(
                'value' => 'advanced',
                'label' => Mage::helper('payex')->__('Advanced (experimental)')
            ),
        );
    }
}
