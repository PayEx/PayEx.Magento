<?php

class PayEx_Payments_TransactionController extends Mage_Core_Controller_Front_Action
{
    /** @var array PayEx TC Spider IPs */
    static protected $_allowed_ips = array(
        '82.115.146.170', // Production
        '82.115.146.10' // Test
    );

    /**
     * PayEx Transaction Callback
     * @see http://www.payexpim.com/quick-guide/9-transaction-callback/
     * @return mixed
     */
    public function indexAction()
    {
        /**
         * Test it using:
         * curl --verbose http://www.xxxxx.xxx/index.php/payex/transaction -d "transactionRef=81596cd7410546c68c1f6046c&transactionNumber=40805420&orderRef=503e6fba843447bb892c70912bbffbde&zzzz=must be here to get http post to work" --location
         */
        $remote_addr = Mage::helper('core/http')->getRemoteAddr();
        Mage::helper('payex/tools')->addToDebug('TC: Requested from: ' . $remote_addr);

        // Check is PayEx Request
        if (!in_array($remote_addr, self::$_allowed_ips)) {
            Mage::helper('payex/tools')->addToDebug('TC: Access denied for this request. It\'s not PayEx Spider.');

            $this->getResponse()
                ->setHeader('HTTP/1.1', 403, true)
                ->setBody('Access denied. Accept PayEx Transaction Callback only.');
            return;
        }

        // is Post
        if (!$this->getRequest()->isPost()) {
            Mage::helper('payex/tools')->addToDebug('TC: Error: Empty request received.');

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Check Post Fields
        if (!$this->getRequest()->isPost() || count($this->getRequest()->getPost()) == 0) {
            Mage::helper('payex/tools')->addToDebug('TC: Requested Params: ' . var_export($this->getRequest()->getPost(), true));
            Mage::helper('payex/tools')->addToDebug('TC: Error: Empty request received.');

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Detect Payment Method of Order
        $order_id = $this->getRequest()->getPost('orderId');

        /**
         * @var Mage_Sales_Model_Order @order
         */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::helper('payex/tools')->addToDebug('TC: Error: OrderID ' . $order_id . ' not found on store.');

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Check Payment Method
        if (strpos($order->getPayment()->getMethodInstance()->getCode(), 'payex_') === false) {
            Mage::helper('payex/tools')->addToDebug('TC: Unsupported payment method: ' . $order->getPayment()->getMethodInstance()->getCode());

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Get Payment Method instance
        $payment_method = $order->getPayment()->getMethodInstance();

        // Get Account Details
        $accountNumber = $payment_method->getConfigData('accountnumber', $order->getStoreId());
        $encryptionKey = $payment_method->getConfigData('encryptionkey', $order->getStoreId());
        $debug = (bool)$payment_method->getConfigData('debug', $order->getStoreId());

        // Check Requested Account Number
        if ($this->getRequest()->getPost('accountNumber') !== $accountNumber) {
            Mage::helper('payex/tools')->addToDebug(
                'TC: Error: Can\'t to get account details of : ' . $this->getRequest()->getPost('accountNumber')
            );

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Define PayEx Settings
        Mage::helper('payex/api')->getPx()->setEnvironment($accountNumber, $encryptionKey, $debug);

        // Get Transaction Details
        $transactionId = $this->getRequest()->getPost('transactionNumber');

        // Lookup Transaction
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', $transactionId);
        if (!empty($collection)) {
            Mage::helper('payex/tools')->addToDebug(sprintf('TC: Transaction %s already processed.', $transactionId));

            $this->getResponse()
                ->setHeader('HTTP/1.1', 200, true)
                ->setBody('FAILURE');
            return;
        }

        // Get current transaction info
        $lastTransactionId = $order->getPayment()->getLastTransId();
        $lastTransactionStatus = null;

        if ($lastTransactionId) {
            $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                ->addAttributeToFilter('txn_id', $lastTransactionId);
            if ($collection->getSize() > 0 && $lastTransaction = $collection->getFirstItem()) {
                // Get Transaction Details
                $lastTransactionDetails = $lastTransaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
                if (!is_array($lastTransactionDetails) || count($lastTransactionDetails) === 0) {
                    $lastTransactionDetails = $payment_method->fetchTransactionInfo($order->getPayment(), $lastTransactionId);
                    $lastTransaction->setAdditionalInformation(Transaction::RAW_DETAILS, $lastTransactionDetails);
                    $lastTransaction->save();
                }

                $lastTransactionStatus = (int) $lastTransactionDetails['transactionStatus'];
            }
        }

        // Call PxOrder.GetTransactionDetails2
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        );

        $details = Mage::helper('payex/api')->getPx()->GetTransactionDetails2($params);
        Mage::helper('payex/tools')->debugApi($details, 'PxOrder.GetTransactionDetails2');
        if ($details['code'] !== 'OK' || $details['errorCode'] !== 'OK') {
            Mage::helper('payex/tools')->addToDebug('TC: Failed to Get Transaction Details.');
            return;
        }

        $order_id = $details['orderId'];
        $transaction_status = (int)$details['transactionStatus'];

        Mage::helper('payex/tools')->addToDebug('TC: Incoming transaction: ' . $transactionId);
        Mage::helper('payex/tools')->addToDebug('TC: Transaction Status: ' . $transaction_status);
        Mage::helper('payex/tools')->addToDebug('TC: OrderId: ' . $order_id);

        // Get Order Status from External Payment Module
        switch ($payment_method->getCode()) {
            case 'payex_bankdebit':
                $order_status_authorize = $payment_method->getConfigData('order_status');
                $order_status_capture = $payment_method->getConfigData('order_status');
                break;
            default:
                $order_status_authorize = $payment_method->getConfigData('order_status_authorize');
                $order_status_capture = $payment_method->getConfigData('order_status_capture');
                break;
        }

        // Save Transaction
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::helper('payex/order')->processPaymentTransaction($order, $details);

        // Check Order and Transaction Result
        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 0;
            case 1;
            case 3;
            case 6:
                // Complete order
                Mage::helper('payex/tools')->addToDebug('TC: Action: Complete order');

                // Call PxOrder.Complete
                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $this->getRequest()->getPost('orderRef'),
                );
                $result = Mage::helper('payex/api')->getPx()->Complete($params);
                Mage::helper('payex/tools')->debugApi($result, 'PxOrder.Complete');
                if ($result['errorCodeSimple'] !== 'OK') {
                    Mage::helper('payex/tools')->addToDebug('TC: Failed to complete payment.');

                    $this->getResponse()
                        ->setHeader('HTTP/1.1', 200, true)
                        ->setBody('FAILURE');
                    return;
                }

                // Verify transaction status
                if ((int)$result['transactionStatus'] !== $transaction_status) {
                    Mage::helper('payex/tools')->addToDebug(
                        'TC: Failed to complete payment. Transaction status is different!'
                    );

                    $this->getResponse()
                        ->setHeader('HTTP/1.1', 200, true)
                        ->setBody('FAILURE');
                    return;
                }

                // Select Order Status
                if (in_array($transaction_status, array(0, 6))) {
                    $new_status = $order_status_capture;
                } elseif ($transaction_status === 3 || (isset($result['pending']) && $result['pending'] === 'true')) {
                    $new_status = $order_status_authorize;
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

                // Create Invoice for Sale Transaction
                if (in_array($transaction_status, array(0, 6))) {
                    $invoice = Mage::helper('payex/order')->makeInvoice($order, false);
                    $invoice->setTransactionId($transactionId);
                    $invoice->save();

                    // Update Order Totals: "Total Due" on Sale Transactions bugfix
                    if ($transaction_status === 0) {
                        $order->setTotalPaid($order->getTotalDue());
                        $order->setBaseTotalPaid($order->getBaseTotalDue());
                        $order->setTotalDue($order->getTotalDue() - $order->getTotalPaid());
                        $order->getBaseTotalDue($order->getBaseTotalDue() - $order->getBaseTotalPaid());

                        // Update Order Totals because API V2 don't update order totals
                        /** @var $invoice Mage_Sales_Model_Order_Invoice */
                        $invoice = Mage::getResourceModel('sales/order_invoice_collection')
                            ->setOrderFilter($order->getId())->getFirstItem();

                        $order->setTotalInvoiced($order->getTotalInvoiced() + $invoice->getGrandTotal());
                        $order->setBaseTotalInvoiced($order->getBaseTotalInvoiced() + $invoice->getBaseGrandTotal());
                        $order->setSubtotalInvoiced($order->getSubtotalInvoiced() + $invoice->getSubtotal());
                        $order->setBaseSubtotalInvoiced($order->getBaseSubtotalInvoiced() + $invoice->getBaseSubtotal());
                        $order->setTaxInvoiced($order->getTaxInvoiced() + $invoice->getTaxAmount());
                        $order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() + $invoice->getBaseTaxAmount());
                        $order->setHiddenTaxInvoiced($order->getHiddenTaxInvoiced() + $invoice->getHiddenTaxAmount());
                        $order->setBaseHiddenTaxInvoiced($order->getBaseHiddenTaxInvoiced() + $invoice->getBaseHiddenTaxAmount());
                        $order->setShippingTaxInvoiced($order->getShippingTaxInvoiced() + $invoice->getShippingTaxAmount());
                        $order->setBaseShippingTaxInvoiced($order->getBaseShippingTaxInvoiced() + $invoice->getBaseShippingTaxAmount());
                        $order->setShippingInvoiced($order->getShippingInvoiced() + $invoice->getShippingAmount());
                        $order->setBaseShippingInvoiced($order->getBaseShippingInvoiced() + $invoice->getBaseShippingAmount());
                        $order->setDiscountInvoiced($order->getDiscountInvoiced() + $invoice->getDiscountAmount());
                        $order->setBaseDiscountInvoiced($order->getBaseDiscountInvoiced() + $invoice->getBaseDiscountAmount());
                        $order->setBaseTotalInvoicedCost($order->getBaseTotalInvoicedCost() + $invoice->getBaseCost());
                    }
                }

                $order->save();
                $order->sendNewOrderEmail();
                break;
            case 2:
                // @todo Improve this method
                // Create CreditMemo
                Mage::helper('payex/tools')->addToDebug('TC: Action: Create CreditMemo');
                if ($order->hasInvoices() && $order->canCreditmemo() && !$order->hasCreditmemos()) {
                    $credit_amount = (float)($details['creditAmount'] / 100);

                    // Get Order Invoices
                    $invoices = Mage::getResourceModel('sales/order_invoice_collection')
                        ->setOrderFilter($order->getId());

                    foreach ($invoices as $invoice) {
                        $invoice->setOrder($order);
                        $invoice_id = $invoice->getIncrementId();
                        // @todo Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND
                        Mage::helper('payex/order')->makeCreditMemo($order, $invoice, $credit_amount, false, $transactionId);
                        Mage::helper('payex/tools')->addToDebug('TC: InvoiceId ' . $invoice_id . ' refunded', $order_id);
                        // @note: Create CreditMemo for first Invoice only
                        break;
                    }
                }
                break;
            case 4;
            case 5:
                // Change Order Status to Canceled
                Mage::helper('payex/tools')->addToDebug('TC: Action: Cancel order');

                // Check current status
                if (in_array($lastTransactionStatus, ['0', '3', '6'])) {
                    Mage::helper('payex/tools')->addToDebug('Order won\'t be cancelled because already paid', $order_id);
                    break;
                }

                if (!$order->isCanceled() && !$order->hasInvoices()) {
                    $message = Mage::helper('payex')->__('Order canceled by Transaction Callback');

                    $order->cancel();
                    $order->addStatusHistoryComment($message);
                    $order->save();
                    $order->sendOrderUpdateEmail(true, $message);

                    Mage::helper('payex/tools')->addToDebug('TC: OrderId ' . $order_id . ' canceled', $order_id);
                }
                break;
            default:
                $this->getResponse()
                    ->setHeader('HTTP/1.1', 200, true)
                    ->setBody('FAILURE');

                return;
        }

        // Show "OK"
        Mage::helper('payex/tools')->addToDebug('TC: Done.');

        $this->getResponse()
            ->setHeader('HTTP/1.1', 200, true)
            ->setBody('OK');
    }
}
