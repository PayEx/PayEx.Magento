<?php
class AAIT_PartPayment_Model_Fee extends Mage_Core_Model_Abstract
{
    public function getPaymentFee()
    {
        $fee = (float) Mage::getSingleton('partpayment/payment')->getConfigData('paymentfee');
        return $fee;
    }
}
