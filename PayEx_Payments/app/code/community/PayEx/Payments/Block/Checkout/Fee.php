<?php

class PayEx_Payments_Block_Checkout_Fee extends Mage_Checkout_Block_Total_Default
{
    protected $_template = 'payex/checkout/fee.phtml';

    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    /**
     * Get Payment fee
     * @return float
     */
    public function getPaymentFee()
    {
        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if (!in_array($paymentMethod, self::$_allowed_methods) && (!count($this->getQuote()->getPaymentsCollection()) || !$this->getQuote()->getPayment()->hasMethodInstance())) {
            return $this;
        }

        $paymentMethod = $this->getQuote()->getPayment()->getMethodInstance();
        if (!in_array($paymentMethod->getCode(), self::$_allowed_methods)) {
            return $this;
        }

        $fee = (float) $paymentMethod->getConfigData('paymentfee');
        return $fee;
    }

    /**
     * Get Quote
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getModel('checkout/cart')->getQuote();
    }

}
