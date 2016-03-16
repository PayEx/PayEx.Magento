<?php

class PayEx_Payments_Model_Source_MediaDistribution
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 1,
                'label' => Mage::helper('payex')->__('Paper by mail')
            ),
            array(
                'value' => 11,
                'label' => Mage::helper('payex')->__('PDF by e-mail')
            ),
        );
    }
}
