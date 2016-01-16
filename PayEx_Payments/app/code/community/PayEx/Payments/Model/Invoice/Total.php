<?php

class PayEx_Payments_Model_Invoice_Total extends Mage_Sales_Model_Order_Invoice_Total_Abstract
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

        if ($order->getBasePayexPaymentFee()) {
            $baseInvoiceTotal = $invoice->getBaseGrandTotal();
            $invoiceTotal = $invoice->getGrandTotal();

            $baseInvoiceTotal = $baseInvoiceTotal + $order->getBasePayexPaymentFee();
            $invoiceTotal = $invoiceTotal + $order->getPayexPaymentFee();

            $invoice->setBaseGrandTotal($baseInvoiceTotal);
            $invoice->setGrandTotal($invoiceTotal);
        }
    }
}
