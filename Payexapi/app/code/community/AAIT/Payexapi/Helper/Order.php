<?php
/**
 * PayEx API Helper: Order Tools
 * Created by AAIT Team.
 */
class AAIT_Payexapi_Helper_Order extends Mage_Core_Helper_Abstract
{
    /**
     * Create transaction
     * @note: Use for only first transaction
     * @param $payment
     * @param $parentTransactionId
     * @param $transactionId
     * @param $type
     * @param int $IsTransactionClosed
     * @param array $fields
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    public function createTransaction(&$payment, $parentTransactionId, $transactionId, $type, $IsTransactionClosed = 0, $fields = array())
    {
        $failsafe = true;
        $shouldCloseParentTransaction = true;

        // set transaction parameters
        $transaction = Mage::getModel('sales/order_payment_transaction')
            ->setOrderPaymentObject($payment)
            ->setTxnType($type)
            ->setTxnId($transactionId)
            ->isFailsafe($failsafe);

        $transaction->setIsClosed($IsTransactionClosed);

        // Set transaction addition information
        if (count($fields) > 0) {
            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
        }

        // link with sales entities
        $payment->setLastTransId($transactionId);
        $payment->setCreatedTransaction($transaction);
        $payment->getOrder()->addRelatedObject($transaction);

        // link with parent transaction
        if ($parentTransactionId) {
            $transaction->setParentTxnId($parentTransactionId);
            // Close parent transaction
            if ($shouldCloseParentTransaction) {
                $parentTransaction = $payment->getTransaction($parentTransactionId);
                if ($parentTransaction) {
                    $parentTransaction->isFailsafe($failsafe)->close(false);
                    $payment->getOrder()->addRelatedObject($parentTransaction);
                }
            }
        }

        return $transaction;
    }

    /**
     * Create Invoice
     * @param $order
     * @param bool $online
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function makeInvoice(&$order, $online = false)
    {

        if ($order->canInvoice() == false) {
            // when order cannot create invoice, need to have some logic to take care
            $order->addStatusToHistory(
                $order->getStatus(), // keep order status/state
                Mage::helper('paygate')->__('Error in creating an invoice', true),
                true /* notified */
            );
            return false;
        }

        // Prepare Invoice
        $magento_version = Mage::getVersion();
        if (version_compare($magento_version, '1.4.2', '>=')) {
            $invoice = Mage::getModel('sales/order_invoice_api_v2');
            $invoice_id = $invoice->create($order->getIncrementId(), $order->getAllItems(), Mage::helper('payexapi')->__('Auto-generated from PayEx module'), false, false);
            $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoice_id);

            if ($online) {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->capture()->save();
            } else {
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->pay()->save();
            }
        } else {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->addComment(Mage::helper('payexapi')->__('Auto-generated from PayEx module'), false, false);
            $invoice->setRequestedCaptureCase($online ? Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE : Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $invoice->getOrder()->setIsInProcess(true);

            try {
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();
            } catch (Mage_Core_Exception $e) {
                // Save Error Message
                $order->addStatusToHistory(
                    $order->getStatus(),
                    'Failed to create invoice: ' . $e->getMessage(),
                    true
                );
                Mage::throwException($e->getMessage());
            }
        }

        $invoice->setIsPaid(true);

        // Assign Last Transaction Id with Invoice
        $transactionId = $invoice->getOrder()->getPayment()->getLastTransId();
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
            $invoice->save();
        }

        return $invoice;
    }

    /**
     * Change Order State, using Direct SQL
     * @param $order_id
     * @param $state
     * @return bool
     */
    public function changeOrderState($order_id, $state)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        try {
            $query = "UPDATE `" . Mage::getSingleton('core/resource')->getTableName('sales_flat_order') . "` SET state='$state', status='$state' WHERE increment_id = '$order_id';";
            $db->query($query);
            $query = "UPDATE `" . Mage::getSingleton('core/resource')->getTableName('sales_flat_order_grid') . "` SET status='$state' WHERE increment_id = '$order_id';";
            $db->query($query);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Get First Transaction ID
     * @param  $order Mage_Sales_Model_Order
     * @return bool
     */
    static public function getFirstTransactionId(&$order)
    {
        $order_id = $order->getId();
        if (!$order_id) {
            return false;
        }
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addOrderIdFilter($order_id)
            ->setOrder('transaction_id', 'ASC')
            ->setPageSize(1)
            ->setCurPage(1);
        return $collection->getFirstItem()->getTxnId();
    }


    /**
     * Create CreditMemo
     * @param $order
     * @param $invoice
     * @param $amount
     * @param bool $online
     * @param null $transactionId
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function makeCreditMemo(&$order, &$invoice, $amount, $online = false, $transactionId = null)
    {
        $service = Mage::getModel('sales/service_order', $order);

        // Prepare CreditMemo
        if ($invoice) {
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
        } else {
            $creditmemo = $service->prepareCreditmemo();
        }
        $creditmemo->addComment(Mage::helper('payexapi')->__('Auto-generated from PayEx module'));

        // Refund
        if (!$online) {
            $creditmemo->setPaymentRefundDisallowed(true);
        }
        //$creditmemo->setRefundRequested(true);
        $invoice->getOrder()->setBaseTotalRefunded(0);
        $creditmemo->setBaseGrandTotal($amount);
        $creditmemo->register()->refund();
        $creditmemo->save();

        // Add transaction Id
        if ($transactionId) {
            $creditmemo->setTransactionId($transactionId);
        }
        // Save CreditMemo
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($creditmemo->getOrder());
        if ($creditmemo->getInvoice()) {
            $transactionSave->addObject($creditmemo->getInvoice());
        }
        $transactionSave->save();

        return $creditmemo;
    }

    /**
     * Rollback stock
     * @param $order
     * @return void
     */
    public function rollbackStockItems(&$order)
    {
        $items = $order->getAllItems(); // Get all items from the order
        if ($items) {
            foreach ($items as $item) {
                if ($item->getParentItem()) {
                    continue;
                }
                $quantity = $item->getQtyOrdered(); // get Qty ordered
                $product_id = $item->getProductId(); // get it's ID
                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id); // Load the stock for this product
                $stock->setQty($stock->getQty() + $quantity); // Set to new Qty
                $stock->save(); // Save
            }
        }

    }

}