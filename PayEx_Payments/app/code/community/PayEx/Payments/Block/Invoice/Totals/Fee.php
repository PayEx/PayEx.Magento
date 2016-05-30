<?php

class PayEx_Payments_Block_Invoice_Totals_Fee extends Mage_Core_Block_Abstract
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $invoice = $parent->getInvoice();
        $paymentMethod = $invoice->getOrder()->getPayment()->getMethodInstance()->getCode();

        if (!in_array($paymentMethod, self::$_allowed_methods)) {
            return $this;
        }

        if ($invoice->getOrder()->getBasePayexPaymentFeeTax()) {
            $total = new Varien_Object();
            $total->setLabel(Mage::helper('payex')->__('Payment fee (Incl.Tax)'));
            $total->setValue($invoice->getOrder()->getPayexPaymentFee() + $invoice->getOrder()->getPayexPaymentFeeTax());
            $total->setBaseValue($invoice->getOrder()->getPayexBasePaymentFee() + $invoice->getOrder()->getPayexBasePaymentFeeTax());
            $total->setCode('payex_payment_fee_with_tax');
            $parent->addTotalBefore($total, 'tax');
        }

        if ($invoice->getOrder()->getBasePayexPaymentFee()) {
            $total = new Varien_Object();
            $total->setLabel(Mage::helper('payex')->__('Payment fee (Excl.Tax)'));
            $total->setValue($invoice->getOrder()->getPayexPaymentFee());
            $total->setBaseValue($invoice->getOrder()->getPayexBasePaymentFee());
            $total->setCode('payex_payment_fee');
            $parent->addTotalBefore($total, 'payex_payment_fee_with_tax');
        }

        return $this;
    }
}
