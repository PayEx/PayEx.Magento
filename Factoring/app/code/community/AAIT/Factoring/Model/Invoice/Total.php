<?php

class AAIT_Factoring_Model_Invoice_Total extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
        if ($order->getPayment()->getMethodInstance()->getCode() !== 'factoring') {
            return $this;
        }

        if ($order->getBaseFactoringPaymentFee()) {
            $baseInvoiceTotal = $invoice->getBaseGrandTotal();
            $invoiceTotal = $invoice->getGrandTotal();

            $baseInvoiceTotal = $baseInvoiceTotal + $order->getBaseFactoringPaymentFee();
            $invoiceTotal = $invoiceTotal + $order->getFactoringPaymentFee();

            $invoice->setBaseGrandTotal($baseInvoiceTotal);
            $invoice->setGrandTotal($invoiceTotal);
        }
    }
}