<?php
class AAIT_Factoring_Model_Fee extends Mage_Core_Model_Abstract
{
    public function getPaymentFee()
    {
        $fee = (float) Mage::getSingleton('factoring/payment')->getConfigData('paymentfee');
        return $fee;
    }
}
