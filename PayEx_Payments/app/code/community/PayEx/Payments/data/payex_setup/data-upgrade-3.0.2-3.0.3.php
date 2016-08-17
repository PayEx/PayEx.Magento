<?php
$orders = Mage::getModel('sales/order')->getCollection();
$orders->getSelect()->join(
    array('p' => $orders->getResource()->getTable('sales/order_payment')),
    'p.parent_id = main_table.entity_id',
    array()
);
$orders->addFieldToFilter('method', array('in' => array('payex_financing', 'payex_invoice', 'payex_partpayment')));
foreach ($orders as $order) {
    /** @var Mage_Sales_Model_Order $order */
    try {
        $method = $order->getPayment()->getMethodInstance()->getCode();
    } catch (Exception $e) {
        continue;
    }

    // Process Invoices
    if ($order->hasInvoices()) {
        $update = false;
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            if ($order->getBasePayexPaymentFee() > 0 && !$invoice->getBasePayexPaymentFee()) {
                $invoice->setBasePayexPaymentFee($order->getBasePayexPaymentFee());
                $invoice->setPayexPaymentFee($order->getPayexPaymentFee());
                $invoice->setBasePayexPaymentFeeTax($order->getBasePayexPaymentFeeTax());
                $invoice->setPayexPaymentFeeTax($order->getPayexPaymentFeeTax());
                $invoice->save();

                $update = true;
            }
        }

        if ($update) {
            $order->setBasePayexPaymentFeeInvoiced($order->getBasePayexPaymentFee());
            $order->setPayexPaymentFeeInvoiced($order->getPayexPaymentFee());
            $order->setBasePayexPaymentFeeTaxInvoiced($order->getBasePayexPaymentFeeTax());
            $order->setPayexPaymentFeeTaxInvoiced($order->getPayexPaymentFeeTax());
            $order->save();
        }
    }

    // Process CreditMemos
    if ($order->hasCreditmemos()) {
        $update = false;
        $creditmemos = $order->getCreditmemosCollection();
        foreach ($creditmemos as $creditmemo) {
            if ($order->getBasePayexPaymentFee() > 0 && !$creditmemo->getBasePayexPaymentFee()) {
                $creditmemo->setBasePayexPaymentFee($order->getBasePayexPaymentFee());
                $creditmemo->setPayexPaymentFee($order->getPayexPaymentFee());
                $creditmemo->setBasePayexPaymentFeeTax($order->getBasePayexPaymentFeeTax());
                $creditmemo->setPayexPaymentFeeTax($order->getPayexPaymentFeeTax());
                $creditmemo->save();

                $update = true;
            }
        }

        if ($update) {
            $order->setBasePayexPaymentFeeRefunded($order->getBasePayexPaymentFee());
            $order->setPayexPaymentFeeRefunded($order->getPayexPaymentFee());
            $order->setBasePayexPaymentFeeTaxRefunded($order->getBasePayexPaymentFeeTax());
            $order->setPayexPaymentFeeTaxRefunded($order->getPayexPaymentFeeTax());
        }
    }
}
