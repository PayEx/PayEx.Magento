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

        if ($parent->getOrder()->getBasePayexPaymentFee() > 0) {
            if ($this->displaySalesPayExFeeBoth()) {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee (Incl.Tax)'));
                $total->setValue($parent->getOrder()->getPayexPaymentFee() + $parent->getOrder()->getPayexPaymentFeeTax());
                $total->setBaseValue($parent->getOrder()->getPayexBasePaymentFee() + $parent->getOrder()->getPayexBasePaymentFeeTax());
                $total->setCode('payex_payment_fee_with_tax');
                $parent->addTotalBefore($total, 'grand_total');

                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee (Excl.Tax)'));
                $total->setValue($parent->getOrder()->getPayexPaymentFee());
                $total->setBaseValue($parent->getOrder()->getPayexBasePaymentFee());
                $total->setCode('payex_payment_fee');
                $parent->addTotalBefore($total, 'payex_payment_fee_with_tax');
            } elseif ($this->displaySalesPayExFeeInclTax()) {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee'));
                $total->setValue($parent->getOrder()->getPayexPaymentFee() + $parent->getOrder()->getPayexPaymentFeeTax());
                $total->setBaseValue($parent->getOrder()->getPayexBasePaymentFee() + $parent->getOrder()->getPayexBasePaymentFeeTax());
                $total->setCode('payex_payment_fee_with_tax');
                $parent->addTotalBefore($total, 'grand_total');
            } else {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee'));
                $total->setValue($parent->getOrder()->getPayexPaymentFee());
                $total->setBaseValue($parent->getOrder()->getPayexBasePaymentFee());
                $total->setCode('payex_payment_fee');
                $parent->addTotalBefore($total, 'grand_total');
            }
        }

        return $this;
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @return mixed
     */
    public function displaySalesPayExFeeBoth()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displaySalesPayExFeeBoth($this->getParentBlock()->getOrder()->getStore());
    }

    /**
     * Check if display cart prices fee included tax
     * @return mixed
     */
    public function displaySalesPayExFeeInclTax()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displaySalesPayExFeeInclTax($this->getParentBlock()->getOrder()->getStore());
    }
}
