<?php

class PayEx_Payments_Model_Invoice_Tax extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        if (!in_array($paymentMethod, self::$_allowed_methods)) {
            return $this;
        }

        if ($order->getBasePayexPaymentFeeTax()) {
            $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $order->getBasePayexPaymentFeeTax());
            $invoice->setTaxAmount($invoice->getTaxAmount() + $order->getPayexPaymentFeeTax());

            $invoice->setBasePayexPaymentFeeTax($order->getBasePayexPaymentFeeTax());
            $invoice->setPayexPaymentFeeTax($order->getPayexPaymentFeeTax());

            $order->setBasePayexPaymentFeeTaxInvoiced($order->getBasePayexPaymentFeeTax());
            $order->setPayexPaymentFeeTaxInvoiced($order->getPayexPaymentFeeTax());
        }
    }
}