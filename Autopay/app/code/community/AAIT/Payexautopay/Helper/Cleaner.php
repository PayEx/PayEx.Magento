<?php

/**
 * PayEx AutoPay Helper: Pending Cleaner
 * Created by AAIT Team.
 */
class AAIT_Payexautopay_Helper_Cleaner extends Mage_Core_Helper_Abstract
{

    /**
     * Cancel orders by State and Time
     * @param $state
     * @param $clean_time
     */
    public function forceCancel($state, $clean_time)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $query = "SELECT increment_id FROM `" . Mage::getSingleton('core/resource')->getTableName('sales_flat_order') . "` WHERE status = '$state' AND created_at < '$clean_time';";
        $results = $db->fetchAll($query);
        if (count($results) > 0) {
            foreach ($results as $result) {
                if (isset($result['increment_id'])) {
                    $order_id = $result['increment_id'];
                    // Load Order
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($order_id);

                    // Use this action for PayexAutopay Orders only. Skip others...
                    $code = $order->getPayment()->getMethodInstance()->getCode();
                    if ($code !== 'payexautopay') {
                        continue;
                    }

                    // Check order state
                    if (!$order->isCanceled() && !$order->hasInvoices()) {
                        // Cancel order
                        $order->cancel()->save();
                        // Add to Log
                        Mage::helper('payexautopay/tools')->addToDebug('Pending Clean(' . $state . '): OrderID ' . $order_id . ' is canceled.');
                    }
                }
            }
        }
    }
}