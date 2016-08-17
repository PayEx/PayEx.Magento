<?php

class PayEx_Payments_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Clear Pending Orders via Cron
     * @return $this
     */
    public function clear_pending_orders()
    {
        $orders = Mage::getModel('sales/order')->getCollection();
        $orders->getSelect()->join(
            array('p' => $orders->getResource()->getTable('sales/order_payment')),
            'p.parent_id = main_table.entity_id',
            array()
        );
        $orders->addFieldToFilter('method', array('like' => 'payex_%'));
        $orders->addFieldToFilter('status', array('in' => array('pending_payment')));
        //$orders->addFieldToFilter('created_at', array('from' => $from, 'to' => $to));
        foreach ($orders as $order) {
            /** @var $order Mage_Sales_Model_Order */
            // Check order state
            if (!$order->isCanceled() && !$order->hasInvoices()) {
                try {
                    $clean_time = -1 * (int)$order->getPayment()->getMethodInstance()->getConfigData('cleantime');
                    if ($clean_time !== 0) {
                        $clean_time = strtotime($clean_time . ' minutes');
                        $order_created_time = strtotime($order->getCreatedAt());
                        if ($clean_time > $order_created_time) {
                            // Cancel order
                            $order->cancel()->save();

                            // Add to Log
                            Mage::helper('payex/tools')->addToDebug('Pending Clean: OrderID ' . $order->getIncrementId() . ' is canceled.');
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }

        return $this;
    }

    /**
     * Change Order Status on Invoice Generation
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function sales_order_invoice_save_after(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        $method = $payment->getMethodInstance();

        $code = $method->getCode();
        if (strpos($code, 'payex_') === false) {
            return $this;
        }

        // is Captured
        if (!$payment->getIsTransactionPending()) {
            // Load Invoice transaction Data
            if (!$invoice->getTransactionId()) {
                return $this;
            }

            $transactionId = $invoice->getTransactionId();
            $details = $method->fetchTransactionInfo($payment, $transactionId);

            if (!isset($details['transactionStatus'])) {
                return $this;
            }

            // Get Order Status
            if ($code === 'payex_bankdebit') {
                // Bankdebit
                $new_status = $method->getConfigData('order_status');
            } elseif (in_array((int)$details['transactionStatus'], array(0, 6))) {
                // For Capture
                $new_status = $method->getConfigData('order_status_capture');
            } elseif ((int)$details['transactionStatus'] === 3) {
                // For Authorize
                $new_status = $method->getConfigData('order_status_authorize');
            } else {
                $new_status = $order->getStatus();
            }

            // Get Order Status
            /** @var Mage_Sales_Model_Order_Status $status */
            $status = Mage::helper('payex/order')->getAssignedStatus($new_status);

            // Change order status
            $order->setData('state', $status->getState());
            $order->setStatus($status->getStatus());
            $order->addStatusHistoryComment(Mage::helper('payex')->__('Order has been paid'), $new_status);
            $order->save();
        }
        return $this;
    }

    /**
     * Collects Payment Fee from quote/addresses to quote
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function sales_quote_collect_totals_after(Varien_Event_Observer $observer)
    {
        /* $quote = $observer->getEvent()->getQuote();
        $quote->setBasePayexPaymentFee(0);
        $quote->setBasePayexPaymentFeeTax(0);
        $quote->setPayexPaymentFee(0);
        $quote->setPayexPaymentFeeTax(0);

        foreach ($quote->getAllAddresses() as $address) {
            $quote->setBasePayexPaymentFee((float)($quote->getBasePayexPaymentFee() + $address->getBasePayexPaymentFee()));
            $quote->setBasePayexPaymentFeeTax((float)($quote->getBasePayexPaymentFeeTax() + $address->getBasePayexPaymentFeeTax()));
            $quote->setPayexPaymentFee((float)($quote->getPayexPaymentFee() + $address->getPayexPaymentFee()));
            $quote->setPayexPaymentFeeTax((float)($quote->getPayexPaymentFeeTax() + $address->getPayexPaymentFeeTax()));
        } */
        return $this;
    }

    /**
     * Adds Payment Fee to order
     * @param Varien_Event_Observer $observer
     */
    public function sales_order_payment_place_end(Varien_Event_Observer $observer)
    {
        $_allowed_methods = array(
            'payex_financing',
            'payex_partpayment',
            'payex_invoice'
        );

        $payment = $observer->getPayment();
        if (!in_array($payment->getMethodInstance()->getCode(), $_allowed_methods)) {
            return;
        }

        $order = $payment->getOrder();
        $order->setBasePayexPaymentFee($order->getQuote()->getBasePayexPaymentFee());
        $order->setBasePayexPaymentFeeTax($order->getQuote()->getBasePayexPaymentFeeTax());
        $order->setPayexPaymentFee($order->getQuote()->getPayexPaymentFee());
        $order->setPayexPaymentFeeTax($order->getQuote()->getPayexPaymentFeeTax());
        $order->save();
    }
}