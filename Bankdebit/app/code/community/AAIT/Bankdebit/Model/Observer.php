<?php
class AAIT_Bankdebit_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Clean Pending Orders via Cron
     * @return AAIT_bankdebit_Model_Observer
     */
    public function cleanPendingOrders()
    {
        // Check UTC TimeZone for Save
        // See http://www.magentocommerce.com/boards/viewthread/40981/
        if (date_default_timezone_get() != Mage_Core_Model_Locale::DEFAULT_TIMEZONE) {
            Mage::throwException('Magento TimeZone Configuration are broken. Use UTC TimeZone.');
        }

        $clean_time = -1 * (int)Mage::getSingleton('bankdebit/payment')->getConfigData('cleantime');
        if ($clean_time !== 0) {
            // Force Cancel Pending orders
            $clean_time = date('Y-m-d H:i:s', strtotime($clean_time . ' minutes'));
            Mage::helper('bankdebit/cleaner')->forceCancel('pending_payment', $clean_time);
            //Mage::helper('bankdebit/cleaner')->forceCancel('pending', $clean_time);
        }

        return $this;
    }

    /**
     * Change Order Status on Invoice Generation
     * @param Varien_Event_Observer $observer
     * @return AAIT_Bankdebit_Model_Observer
     */
    public function sales_order_invoice_save_after(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $payment = $order->getPayment();

        // Use for bankdebit Method only
        $code = $payment->getMethodInstance()->getCode();
        if ($code !== 'bankdebit') {
            return $this;
        }

        // is Captured
        if (!$invoice->getIsPaid() && !$payment->getIsTransactionPending()) {
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
            if (in_array($details['transactionStatus'], array('0', '6'))) {
                // For Capture
                $new_status = Mage::getSingleton('bankdebit/payment')->getConfigData('order_status');
            }
            if ($details['transactionStatus'] === '3') {
                // For Authorize
                $new_status = Mage::getSingleton('bankdebit/payment')->getConfigData('order_status');
            }
            if (empty($new_status)) {
                $new_status = $order->getStatus();
            }

            // Get Order State
            $status = Mage::getModel('sales/order_status')
                ->getCollection()
                ->joinStates()
                ->addFieldToFilter('main_table.status', $new_status)
                ->getFirstItem();

            // Change order status
            $order->setData('state', $status->getState());
            $order->setStatus($status->getStatus());
            $order->addStatusHistoryComment(Mage::helper('bankdebit')->__('Order has been paid'), $new_status);
            $order->save();
        }

        return $this;
    }
}