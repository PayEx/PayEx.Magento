<?php

class PayEx_Payments_Block_Form_PartPayment extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/partpayment/form.phtml');
    }

    /**
     * Get Saved SSN from SSN extension
     * @return mixed
     */
    public function getPayexSSN()
    {
        return Mage::getSingleton('checkout/session')->getPayexSSN();
    }

    /**
     * Get Payment Fee
     * @return Varien_Object
     */
    public function getPayexPaymentFee()
    {
        $paymentMethod = Mage::getModel('payex/payment_partPayment');
        $price = (float) $paymentMethod->getConfigData('paymentfee');
        $tax_class = $paymentMethod->getConfigData('paymentfee_tax_class');
        $fee = Mage::helper('payex/fee')->getPaymentFeePrice($price, $tax_class);
        return $fee;
    }
}
