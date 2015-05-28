<?php

class AAIT_Factoring_Block_Checkout_Fee extends Mage_Checkout_Block_Total_Default
{
    protected $_template = 'factoring/checkout/fee.phtml';

    /**
     * Get Payment fee
     * @return float
     */
    public function getPaymentFee()
    {
        return Mage::getSingleton('factoring/fee')->getPaymentFee();
    }

}
