<?php
class AAIT_Payexapi_PayexapiController extends Mage_Core_Controller_Front_Action
{
    /** @var array PayEx TC Spider IPs */
    static protected $_allowed_ips = array(
        '82.115.146.170', // Production
        '82.115.146.10' // Test
    );

    /** @var array PayEx Allowed Payment Methods */
    static protected $_allowed_methods = array(
        'bankdebit',
        'payex2',
        'payexautopay',
        'payexinvoice',
        'factoring'
    );

    /*    public function testAction()
    {
        $order_id = '100000165';
        $ref = 131.24;

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);

        $items = $order->getAllItems();
        $qtys = array();
        foreach ($items as $itemId => $item)
        {
            $qtys[$item->getSku()] = 0;
        }
        Mage::log($qtys);

        $data = array(
            'qtys' => array(),
            'shipping_amount' => 0,
            'adjustment_positive' => $ref,
            'adjustment_negative' => 0
        );

        $creditmemo = Mage::getModel('sales/order_creditmemo_api');
        $creditmemoId = $creditmemo->create($order->getIncrementId(), $data = null, Mage::helper('payexapi')->__('Auto-generated from PayEx module'), false, false, $ref);
        exit('Done ' . $creditmemoId);
    }*/

    /**
     * PayEx Transaction Callback
     * @see http://www.payexpim.com/quick-guide/9-transaction-callback/
     * @return mixed
     */
    public function transactionsAction()
    {
        /**
         * Test it using:
         * curl --verbose http://www.xxxxx.xxx/index.php/payexapi/payexapi/transactions -d "transactionRef=81596cd7410546c68c1f6046c&transactionNumber=40805420&orderRef=503e6fba843447bb892c70912bbffbde&zzzz=must be here to get http post to work" --location
         */
        Mage::helper('payexapi/tools')->addToDebug('TC: Requested from: ' . $_SERVER['REMOTE_ADDR']);

        // Check is PayEx Request
        if (!in_array($_SERVER['REMOTE_ADDR'], self::$_allowed_ips)) {
            Mage::helper('payexapi/tools')->addToDebug('TC: Access denied for this request. It\'s not PayEx Spider.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
            header(sprintf('Status: %s %s', '403', 'Access denied. Accept PayEx Transaction Callback only.'), true, '403');
            exit('Error: Access denied. Accept PayEx Transaction Callback only. ');
        }

        // Check Post Fields
        Mage::helper('payexapi/tools')->addToDebug('TC: Requested Params: ' . var_export($_POST, true));
        if (count($_POST) == 0) {
            Mage::helper('payexapi/tools')->addToDebug('TC: Error: Empty request received.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Detect Payment Method of Order
        $order_id = $_POST['orderId'];

        /**
         * @var @order Mage_Sales_Model_Order
         */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::helper('payexapi/tools')->addToDebug('TC: Error: OrderID ' . $order_id . ' not found on store.');
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Check Payment Method
        $payment_method = $order->getPayment()->getMethodInstance();
        $payment_method_code = $payment_method->getCode();
        if (!in_array($payment_method_code, self::$_allowed_methods)) {
            Mage::helper('payexapi/tools')->addToDebug('TC: Unsupported payment method: ' . $payment_method_code);
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Get Account Details
        $accountNumber = $payment_method->getConfigData('accountnumber');
        $encryptionKey = $payment_method->getConfigData('encryptionkey');
        $debug = (bool)$payment_method->getConfigData('debug');

        // Check Requested Account Number
        if ($_POST['accountNumber'] !== $accountNumber) {
            Mage::helper('payexapi/tools')->addToDebug('TC: Error: Can\'t to get account details of : ' . $_POST['accountNumber']);
            header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
            header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
            exit('FAILURE');
        }

        // Define PayEx Settings
        $this->getPx($payment_method_code)->setEnvironment($accountNumber, $encryptionKey, $debug);

        // Get Transaction Details
        $transactionId = $_POST['transactionNumber'];

        // Call PxOrder.GetTransactionDetails2
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        );

        $details = $this->getPx($payment_method_code)->GetTransactionDetails2($params);
        Mage::helper('payexapi/tools')->debugApi($details, 'PxOrder.GetTransactionDetails2');

        if ($details['code'] != 'OK' || $details['errorCode'] != 'OK') {
            Mage::helper('payexapi/tools')->addToDebug('TC: Failed to Get Transaction Details.');
            return;
        }

        $order_id = $details['orderId'];
        $transactionStatus = (int)$details['transactionStatus'];

        Mage::helper('payexapi/tools')->addToDebug('TC: Incoming transaction: ' . $transactionId);
        Mage::helper('payexapi/tools')->addToDebug('TC: Transaction Status: ' . $transactionStatus);
        Mage::helper('payexapi/tools')->addToDebug('TC: OrderId: ' . $order_id);

        // Get Order Status from External Payment Module
        switch ($payment_method_code) {
            case 'bankdebit':
            case 'payexinvoice':
                $order_status_authorize = $payment_method->getConfigData('order_status');
                $order_status_capture = $payment_method->getConfigData('order_status');
                break;
            default:
                $order_status_authorize = $payment_method->getConfigData('order_status_authorize');
                $order_status_capture = $payment_method->getConfigData('order_status_capture');
                break;
        }

        /**
         * @var $payment Mage_Sales_Model_Order_Payment
         */
        $payment = $order->getPayment();

        /* 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transactionStatus) {
            case 0;
            case 3:
                // Complete order
                Mage::helper('payexapi/tools')->addToDebug('TC: Action: Complete order');
                if (Mage::helper('payexapi/order')->getFirstTransactionId($order) == null) {
                    // Call PxOrder.Complete
                    $params = array(
                        'accountNumber' => '',
                        'orderRef' => $_POST['orderRef'],
                    );
                    $result = $this->getPx($payment_method_code)->Complete($params);
                    Mage::helper('payexapi/tools')->debugApi($result, 'PxOrder.Complete');

                    // Check Transaction
                    if (in_array($result['transactionStatus'], array('0', '3', '6'))) {
                        // Detect Transaction type
                        if ($result['transactionStatus'] == '0') {
                            $transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
                            $transaction_closed = 0; // 1. Review this
                        }
                        if ($result['transactionStatus'] == '3') {
                            $transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
                            $transaction_closed = 0;
                        }
                        if ($result['transactionStatus'] == '6') {
                            $transaction_type = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
                            $transaction_closed = 1;
                        }

                        // Save Transaction
                        Mage::helper('payexapi/order')->createTransaction($payment, null, $transactionId, $transaction_type, $transaction_closed, $result);
                        $payment->save();

                        // Set Order State
                        //$order->addStatusHistoryComment(Mage::helper('payexapi')->__('Payment is accepted by Transaction Callback'));
                        $order->save();

                        // Create Invoice for Sale Transaction
                        if ($result['transactionStatus'] == '0' && isset($result['transactionNumber'])) {
                            $invoice = Mage::helper('payexapi/order')->makeInvoice($order, false);

                            // Add transaction Id
                            $invoice->setTransactionId($result['transactionNumber']);
                            $invoice->save();
                        }

                        // Set Order Status
                        $order_status = (in_array($result['transactionStatus'], array('0', '3'))) ? $order_status_capture : $order_status_authorize;
                        $message = Mage::helper('payexapi')->__('Payment is accepted by Transaction Callback');
                        if ($order->isStateProtected($order_status) === true) {
                            // Force change status
                            Mage::helper('payexapi/order')->changeOrderState($order_id, $order_status);
                            $order->addStatusToHistory($order_status, $message, false);
                        } else {
                            $order->setState($order_status, $order_status, $message);
                        }
                        $order->save();

                        Mage::helper('payexapi/tools')->addToDebug('TC: OrderId ' . $order_id . ' Complete', $order_id);
                        break;
                    }

                    // Cancel Order for Other Statuses
                    $order->cancel();
                    $order->addStatusHistoryComment(Mage::helper('payexapi')->__('Order automatically canceled by Transaction Callback.'));
                    $order->save();
                    Mage::helper('payexapi/tools')->addToDebug('TC: OrderId ' . $order_id . ' Complete (canceled)', $order_id);
                }
                break;
            case 2:
                // Create CreditMemo
                Mage::helper('payexapi/tools')->addToDebug('TC: Action: Create CreditMemo');
                if ($order->hasInvoices() && $order->canCreditmemo() && !$order->hasCreditmemos()) {
                    $credit_amount = (float)($details['creditAmount'] / 100);

                    // Get Order Invoices
                    $invoices = Mage::getResourceModel('sales/order_invoice_collection')
                        ->setOrderFilter($order->getId());

                    foreach ($invoices as $invoice) {
                        $invoice->setOrder($order);
                        $invoice_id = $invoice->getIncrementId();
                        Mage::helper('payexapi/order')->createTransaction($payment, $payment->getLastTransId(), $transactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND, 0, $details);
                        Mage::helper('payexapi/order')->makeCreditMemo($order, $invoice, $credit_amount, false, $transactionId);
                        Mage::helper('payexapi/tools')->addToDebug('TC: InvoiceId ' . $invoice_id . ' refunded', $order_id);
                        // @note: Create CreditMemo for first Invoice only
                        break;
                    }
                }
                break;
            case 4;
            case 5:
                // Change Order Status to Canceled
                Mage::helper('payexapi/tools')->addToDebug('TC: Action: Cancel order');
                if (!$order->isCanceled() && !$order->hasInvoices()) {
                    Mage::helper('payexapi/order')->createTransaction($payment, $payment->getLastTransId(), $transactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, 1, $details);

                    // Rollback stock
                    Mage::helper('payexapi/order')->rollbackStockItems($order);

                    // Set Status Canceled
                    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('payexapi')->__('Order canceled by Transaction Callback'));
                    $order->save();

                    Mage::helper('payexapi/tools')->addToDebug('TC: OrderId ' . $order_id . ' canceled', $order_id);
                }
                break;
            case 6:
                // Set Order Status to captured
                Mage::helper('payexapi/tools')->addToDebug('TC: Action: Capture');
                if ($payment->canCapture()) {
                    Mage::helper('payexapi/order')->createTransaction($payment, $payment->getLastTransId(), $transactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, 0, $details);
                    $invoice = Mage::helper('payexapi/order')->makeInvoice($order, false);

                    // Update Order Totals: "Total Due" on Sale Transactions bugfix
                    $order->setTotalPaid($order->getTotalDue());
                    $order->setBaseTotalPaid($order->getBaseTotalDue());
                    $order->setTotalDue($order->getTotalDue() - $order->getTotalPaid());
                    $order->getBaseTotalDue($order->getBaseTotalDue() - $order->getBaseTotalPaid());

                    // Set Order Status
                    $message = Mage::helper('payexapi')->__('Order captured by Transaction Callback');
                    if ($order->isStateProtected($order_status_capture) === true) {
                        // Force change status
                        Mage::helper('payexapi/order')->changeOrderState($order_id, $order_status_capture);
                        $order->addStatusToHistory($order_status_capture, $message, false);
                    } else {
                        $order->setState($order_status_capture, $order_status_capture, $message);
                    }
                    $order->save();

                    Mage::helper('payexapi/tools')->addToDebug('TC: OrderId ' . $order_id . ' captured', $order_id);
                }
                break;
            default:
                Mage::helper('payexapi/tools')->addToDebug('TC: Unknown Transaction Status', $order_id);
                header(sprintf('%s %s %s', 'HTTP/1.1', '500', 'FAILURE'), true, '500');
                header(sprintf('Status: %s %s', '500', 'FAILURE'), true, '500');
                exit('FAILURE');
        }

        // Show "OK"
        Mage::helper('payexapi/tools')->addToDebug('TC: Done.');
        header(sprintf('%s %s %s', 'HTTP/1.1', '200', 'OK'), true, '200');
        header(sprintf('Status: %s %s', '200', 'OK'), true, '200');
        exit('OK');
    }

    /**
     * Get Payex library of Payemnt Method
     * @param $payment_method_code
     * @return mixed
     */
    protected function getPx($payment_method_code)
    {
        $px = Mage::helper($payment_method_code . '/api')->getPx();
        return $px;
    }
}
