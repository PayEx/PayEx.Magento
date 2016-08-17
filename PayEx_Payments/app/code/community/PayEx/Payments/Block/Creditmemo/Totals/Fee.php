<?php

class PayEx_Payments_Block_Creditmemo_Totals_Fee extends Mage_Adminhtml_Block_Template
{
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        if ($parent->getSource()->getBasePayexPaymentFee() > 0) {
            if ($this->displaySalesPayExFeeBoth()) {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee (Incl.Tax)'));
                $total->setValue($parent->getSource()->getPayexPaymentFee() + $parent->getSource()->getPayexPaymentFeeTax());
                $total->setBaseValue($parent->getSource()->getBasePayexPaymentFee() + $parent->getSource()->getBasePayexPaymentFeeTax());
                $total->setCode('payex_payment_fee_with_tax');
                $parent->addTotalBefore($total, 'shipping');

                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee (Excl.Tax)'));
                $total->setValue($parent->getSource()->getPayexPaymentFee());
                $total->setBaseValue($parent->getSource()->getBasePayexPaymentFee());
                $total->setCode('payex_payment_fee');
                $parent->addTotalBefore($total, 'payex_payment_fee_with_tax');
            } elseif ($this->displaySalesPayExFeeInclTax()) {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee'));
                $total->setValue($parent->getSource()->getPayexPaymentFee() + $parent->getSource()->getPayexPaymentFeeTax());
                $total->setBaseValue($parent->getSource()->getBasePayexPaymentFee() + $parent->getSource()->getBasePayexPaymentFeeTax());
                $total->setCode('payex_payment_fee_with_tax');
                $parent->addTotalBefore($total, 'shipping');
            } else {
                $total = new Varien_Object();
                $total->setLabel(Mage::helper('payex')->__('Payment fee'));
                $total->setValue($parent->getSource()->getPayexPaymentFee());
                $total->setBaseValue($parent->getSource()->getBasePayexPaymentFee());
                $total->setCode('payex_payment_fee');
                $parent->addTotalBefore($total, 'shipping');
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
        return $config->displaySalesPayExFeeBoth($this->getParentBlock()->getSource()->getStoreId());
    }

    /**
     * Check if display cart prices fee included tax
     * @return mixed
     */
    public function displaySalesPayExFeeInclTax()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displaySalesPayExFeeInclTax($this->getParentBlock()->getSource()->getStoreId());
    }
}
