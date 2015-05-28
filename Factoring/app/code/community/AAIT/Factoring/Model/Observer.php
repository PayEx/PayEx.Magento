<?php

class AAIT_Factoring_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Clean Pending Orders via Cron
     * @return $this
     */
    public function cleanPendingOrders()
    {
        // Check UTC TimeZone for Save
        // See http://www.magentocommerce.com/boards/viewthread/40981/
        if (date_default_timezone_get() !== Mage_Core_Model_Locale::DEFAULT_TIMEZONE) {
            Mage::throwException('Magento TimeZone Configuration are broken. Use UTC TimeZone.');
        }

        $clean_time = -1 * (int)Mage::getSingleton('factoring/payment')->getConfigData('cleantime');
        if ($clean_time !== 0) {
            // Force Cancel Pending orders
            $clean_time = date('Y-m-d H:i:s', strtotime($clean_time . ' minutes'));
            Mage::helper('factoring/cleaner')->forceCancel('pending_payment', $clean_time);
            //Mage::helper('factoring/cleaner')->forceCancel('pending', $clean_time);
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

        $code = $payment->getMethodInstance()->getCode();
        if ($code !== 'factoring') {
            return $this;
        }

        // is Captured
        if (!$payment->getIsTransactionPending()) {
            // Load Invoice transaction Data
            if (!$invoice->getTransactionId()) {
                return $this;
            }

            $transactionId = $invoice->getTransactionId();
            $details = $payment->getMethodInstance()->fetchTransactionInfo($payment, $transactionId);

            if (!isset($details['transactionStatus'])) {
                return $this;
            }

            // Get Order Status
            if (in_array((int)$details['transactionStatus'], array(0, 6))) {
                // For Capture
                $new_status = Mage::getSingleton('factoring/payment')->getConfigData('order_status_capture');
            }
            if ((int)$details['transactionStatus'] === 3) {
                // For Authorize
                $new_status = Mage::getSingleton('factoring/payment')->getConfigData('order_status_authorize');
            }
            if (empty($new_status)) {
                $new_status = $order->getStatus();
            }

            // Change order status
            $order->setData('state', $new_status);
            $order->setStatus($new_status);
            $order->addStatusHistoryComment(Mage::helper('factoring')->__('Order has been paid'), $new_status);
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
        $quote = $observer->getEvent()->getQuote();

        $quote->setBaseFactoringPaymentFee(0);
        $quote->setFactoringPaymentFee(0);

        foreach ($quote->getAllAddresses() as $address) {
            $quote->setBaseFactoringPaymentFee((float)($quote->getBaseFactoringPaymentFee() + $address->getBaseFactoringPaymentFee()));
            $quote->setFactoringPaymentFee((float)($quote->getFactoringPaymentFee() + $address->getFactoringPaymentFee()));
        }
        return $this;
    }

    /**
     * Adds Payment Fee to order
     * @param Varien_Event_Observer $observer
     */
    public function sales_order_payment_place_end(Varien_Event_Observer $observer)
    {
        $payment = $observer->getPayment();
        if ($payment->getMethodInstance()->getCode() !== 'factoring') {
            return;
        }

        $order = $payment->getOrder();
        $base_fee = $order->getQuote()->getBaseFactoringPaymentFee();
        $fee = $order->getQuote()->getFactoringPaymentFee();

        $order->setBaseFactoringPaymentFee($base_fee);
        $order->setFactoringPaymentFee($fee);
        $order->save();
    }
}