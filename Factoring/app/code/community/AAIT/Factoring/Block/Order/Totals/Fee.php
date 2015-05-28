<?php

class AAIT_Factoring_Block_Order_Totals_Fee extends Mage_Core_Block_Abstract
{
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        if ($parent->getOrder()->getPayment()->getMethodInstance()->getCode() !== 'factoring') {
            return $this;
        }

        if ($parent->getOrder()->getBaseFactoringPaymentFee()) {
            $total = new Varien_Object();
            $total->setLabel($this->__('Payment fee'));
            $total->setValue($parent->getOrder()->getFactoringPaymentFee());
            $total->setBaseValue($parent->getOrder()->getFactoringBasePaymentFee());
            $total->setCode('factoring_payment_fee');
            $parent->addTotalBefore($total, 'tax');
        }

        return $this;
    }
}