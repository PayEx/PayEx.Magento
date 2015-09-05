<?php

class AAIT_PartPayment_Block_Checkout_Fee extends Mage_Checkout_Block_Total_Default
{
    protected $_template = 'partpayment/checkout/fee.phtml';

    /**
     * Get Payment fee
     * @return float
     */
    public function getPaymentFee()
    {
        return Mage::getSingleton('partpayment/fee')->getPaymentFee();
    }

}
