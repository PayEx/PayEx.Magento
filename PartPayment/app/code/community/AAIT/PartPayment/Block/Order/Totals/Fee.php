<?php

class AAIT_PartPayment_Block_Order_Totals_Fee extends Mage_Core_Block_Abstract
{
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        if ($parent->getOrder()->getPayment()->getMethodInstance()->getCode() !== 'partpayment') {
            return $this;
        }

        if ($parent->getOrder()->getBasePartpaymentPaymentFee()) {
            $total = new Varien_Object();
            $total->setLabel($this->__('Payment fee'));
            $total->setValue($parent->getOrder()->getPartpaymentPaymentFee());
            $total->setBaseValue($parent->getOrder()->getPartpaymentBasePaymentFee());
            $total->setCode('partpayment_payment_fee');
            $parent->addTotalBefore($total, 'tax');
        }

        return $this;
    }
}