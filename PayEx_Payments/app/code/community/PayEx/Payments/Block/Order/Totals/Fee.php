<?php

class PayEx_Payments_Block_Order_Totals_Fee extends Mage_Core_Block_Abstract
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $paymentMethod = $parent->getOrder()->getPayment()->getMethodInstance()->getCode();

        if (!in_array($paymentMethod, self::$_allowed_methods)) {
            return $this;
        }

        if ($parent->getOrder()->getBasePayexPaymentFee()) {
            $total = new Varien_Object();
            $total->setLabel(Mage::helper('payex')->__('Payment fee'));
            $total->setValue($parent->getOrder()->getPayexPaymentFee());
            $total->setBaseValue($parent->getOrder()->getPayexBasePaymentFee());
            $total->setCode('payex_payment_fee');
            $parent->addTotalBefore($total, 'tax');
        }

        return $this;
    }
}
