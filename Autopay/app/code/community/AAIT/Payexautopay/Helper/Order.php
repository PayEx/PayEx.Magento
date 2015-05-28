<?php
/**
 * PayEx Helper: Order
 * Created by AAIT Team.
 */

class AAIT_Payexautopay_Helper_Order extends Mage_Core_Helper_Abstract
{
    /**
     * Process Payment Transaction
     * @param Mage_Sales_Model_Order $order
     * @param array                  $fields
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction|null
     * @throws Exception
     */
    public function processPaymentTransaction(Mage_Sales_Model_Order $order, array $fields)
    {
        // Lookup Transaction
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', $fields['transactionNumber']);
        if (count($collection) > 0) {
            Mage::helper('payexautopay/tools')->addToDebug(sprintf('Transaction %s already processed.', $fields['transactionNumber']), $order->getIncrementId());
            return $collection->getFirstItem();
        }

        // Set Payment Transaction Id
        $payment = $order->getPayment();
        $payment->setTransactionId($fields['transactionNumber']);

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = isset($fields['transactionStatus']) ? (int)$fields['transactionStatus'] : null;
        switch ($transaction_status) {
            case 1:
                // From PayEx PIM:
                // "If PxOrder.Complete returns transactionStatus = 1, then check pendingReason for status."
                // See http://www.payexpim.com/payment-methods/paypal/
                if ($fields['pending'] === 'true') {
                    $message = Mage::helper('payexautopay')->__('Transaction Status: %s.', $transaction_status);
                    $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, true, $message);
                    $transaction->setIsClosed(0);
                    $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                    $transaction->setMessage($message);
                    $transaction->save();
                    break;
                }

                $message = Mage::helper('payexautopay')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->save();
                break;
            case 3:
                $message = Mage::helper('payexautopay')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, true, $message);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->save();
                break;
            case 0;
            case 6:
                $message = Mage::helper('payexautopay')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setMessage($message);
                $transaction->save();
                break;
            case 2:
                $message = Mage::helper('payexautopay')->__('Detected an abnormal payment process (Transaction Status: %s).', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->setIsCancel(true);
                $transaction->save();
                break;
            case 4;
                $message = Mage::helper('payexautopay')->__('Order automatically canceled. Transaction is canceled.');
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->setIsCancel(true);
                $transaction->save();
                break;
            case 5;
                $message = Mage::helper('payexautopay')->__('Order automatically canceled. Transaction is failed.');
                $message .= ' ' . Mage::helper('payexautopay/tools')->getVerboseErrorMessage($fields);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->setIsCancel(true);
                $transaction->save();
                break;
            default:
                $message = Mage::helper('payexautopay')->__('Invalid transaction status.');
                $transaction = Mage::getModel('sales/order_payment_transaction');
                $transaction->setMessage($message);
                $transaction->setIsCancel(true);
                break;
        }

        try {
            $order->save();
            Mage::helper('payexautopay/tools')->addToDebug($message, $order->getIncrementId());
        } catch (Exception $e) {
            Mage::helper('payexautopay/tools')->addToDebug('Error: ' . $e->getMessage(), $order->getIncrementId());
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
        // Prepare Invoice
        $magento_version = Mage::getVersion();
        if (version_compare($magento_version, '1.4.2', '>=')) {
            $invoice = Mage::getModel('sales/order_invoice_api_v2');
            $invoice_id = $invoice->create($order->getIncrementId(), $order->getAllItems(), Mage::helper('payexautopay')->__('Auto-generated from PayEx module'), false, false);
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
            $invoice->addComment(Mage::helper('payexautopay')->__('Auto-generated from PayEx module'), false, false);
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
     * Get Order Amount
     * With Using Rounding Issue Fix
     * @param Mage_Sales_Model_Order $order
     * @return float
     */
    public function getOrderAmount($order)
    {
        // At moment this function don't support discounts
        if (abs($order->getDiscountAmount()) > 0) {
            return $order->getGrandTotal();
        }

        $amount = 0;
        // add Order Items
        $items = $order->getAllVisibleItems();
        /** @var $item Mage_Sales_Model_Order_Item */
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getPriceInclTax();
            $amount += round(100 * Mage::app()->getStore()->roundPrice($itemQty * $priceWithTax));
        }

        // add Discount
        $discount = $order->getDiscountAmount();
        $discount += $order->getShippingDiscountAmount();
        $amount += round(100 * $discount);

        // Add reward points
        $amount += -1 * $order->getBaseRewardCurrencyAmount();

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingIncTax = $order->getShippingInclTax();
            $amount += round(100 * $shippingIncTax);
        }

        $grand_total = $order->getGrandTotal();
        $amount = $amount / 100;

        $abs = abs(Mage::app()->getStore()->roundPrice($amount) - Mage::app()->getStore()->roundPrice($grand_total));
        // Is ~0.010000000002037
        if ($abs > 0 && $abs < 0.011) {
            Mage::helper('payexautopay/tools')->addToDebug('Warning: Price rounding issue. ' . $grand_total . ' vs ' . $amount);
            return $amount;
        } else {
            return $grand_total;
        }
    }

    /**
     * Add PayEx Single Order Line
     * @param string $orderRef
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function addOrderLine($orderRef, $order)
    {
        // add Order Items
        $items = $order->getAllVisibleItems();
        $i = 1;
        /** @var $item Mage_Sales_Model_Order_Item */
        foreach ($items as $item) {
            // @todo Calculate prices using Discount Rules
            // @todo Get children products from bundle
            if (!$item->getNoDiscount()) {
                Mage::helper('payexautopay/tools')->addToDebug('Warning: The product has a discount. There might be problems.', $order->getIncrementId());
            }

            $itemQty = (int)$item->getQtyOrdered();
            //$taxPrice = $item->getTaxAmount();
            $taxPrice = Mage::app()->getStore()->roundPrice($itemQty * $item->getPriceInclTax() - $itemQty * $item->getPrice());
            $taxPercent = $item->getTaxPercent();
            $priceWithTax = Mage::app()->getStore()->roundPrice($itemQty * $item->getPriceInclTax());

            // Calculate tax percent for Bundle products
            if ($item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                $taxPercent = ($taxPrice > 0) ? round(100 / (($priceWithTax - $taxPrice) / $taxPrice)) : 0;
            }

            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => $item->getName(),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => $itemQty,
                'amount' => round(100 * $priceWithTax), //must include tax
                'vatPrice' => round(100 * $taxPrice),
                'vatPercent' => round(100 * $taxPercent)
            );

            $result = Mage::helper('payexautopay/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // add Discount
        $discount = $order->getDiscountAmount();

        // exclude shipping discount
        // discount is negative value
        $discount += $order->getShippingDiscountAmount();

        if (abs($discount) > 0) {
            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => ($order->getDiscountDescription() !== null) ? Mage::helper('sales')->__('Discount (%s)', $order->getDiscountDescription()) : Mage::helper('sales')->__('Discount'),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => 1,
                'amount' => round(100 * $discount),
                'vatPrice' => 0,
                'vatPercent' => 0
            );

            $result = Mage::helper('payexautopay/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // Add reward points
        if ((float)$order->getBaseRewardCurrencyAmount() > 0) {
            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => Mage::helper('payexautopay')->__('Reward points'),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => 1,
                'amount' => round(-100 * $order->getBaseRewardCurrencyAmount()), //must include tax
                'vatPrice' => 0,
                'vatPercent' => 0
            );

            $result = Mage::helper('payexautopay/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shipping = $order->getShippingAmount();
            $shippingIncTax = $order->getShippingInclTax();
            $shippingTax = $order->getShippingTaxAmount();

            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => $order->getShippingDescription(),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => 1,
                'amount' => round(100 * $shippingIncTax), //must include tax
                'vatPrice' => round(100 * $shippingTax),
                'vatPercent' => $shipping != 0 ? round((100 * 100 * ($shippingTax) / $shipping)) : 0
            );

            $result = Mage::helper('payexautopay/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }
        return true;
    }

    /**
     * Add Payex Order Address
     * @param $orderRef
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function addOrderAddress($orderRef, $order)
    {
        $billingAddress = $order->getBillingAddress()->getStreet();
        $billingCountryCode = $order->getBillingAddress()->getCountry();
        $billingCountry = Mage::getModel('directory/country')->load($billingCountryCode)->getName();

        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef,
            'billingFirstName' => $order->getBillingAddress()->getFirstname(),
            'billingLastName' => $order->getBillingAddress()->getLastname(),
            'billingAddress1' => $billingAddress[0],
            'billingAddress2' => (isset($billingAddress[1])) ? $billingAddress[1] : '',
            'billingAddress3' => '',
            'billingPostNumber' => (string)$order->getBillingAddress()->getPostcode(),
            'billingCity' => (string)$order->getBillingAddress()->getCity(),
            'billingState' => (string)$order->getBillingAddress()->getRegion(),
            'billingCountry' => $billingCountry,
            'billingCountryCode' => $billingCountryCode,
            'billingEmail' => (string)$order->getBillingAddress()->getEmail(),
            'billingPhone' => (string)$order->getBillingAddress()->getTelephone(),
            'billingGsm' => '',
        );

        // add Shipping
        $shipping_params = array(
            'deliveryFirstName' => '',
            'deliveryLastName' => '',
            'deliveryAddress1' => '',
            'deliveryAddress2' => '',
            'deliveryAddress3' => '',
            'deliveryPostNumber' => '',
            'deliveryCity' => '',
            'deliveryState' => '',
            'deliveryCountry' => '',
            'deliveryCountryCode' => '',
            'deliveryEmail' => '',
            'deliveryPhone' => '',
            'deliveryGsm' => '',
        );

        if (!$order->getIsVirtual()) {
            $deliveryAddress = $order->getShippingAddress()->getStreet();
            $deliveryCountryCode = $order->getShippingAddress()->getCountry();
            $deliveryCountry = Mage::getModel('directory/country')->load($deliveryCountryCode)->getName();

            $shipping_params = array(
                'deliveryFirstName' => $order->getShippingAddress()->getFirstname(),
                'deliveryLastName' => $order->getShippingAddress()->getLastname(),
                'deliveryAddress1' => $deliveryAddress[0],
                'deliveryAddress2' => (isset($deliveryAddress[1])) ? $deliveryAddress[1] : '',
                'deliveryAddress3' => '',
                'deliveryPostNumber' => (string)$order->getShippingAddress()->getPostcode(),
                'deliveryCity' => (string)$order->getShippingAddress()->getCity(),
                'deliveryState' => (string)$order->getShippingAddress()->getRegion(),
                'deliveryCountry' => $deliveryCountry,
                'deliveryCountryCode' => $deliveryCountryCode,
                'deliveryEmail' => (string)$order->getShippingAddress()->getEmail(),
                'deliveryPhone' => (string)$order->getShippingAddress()->getTelephone(),
                'deliveryGsm' => '',
            );
        }
        $params += $shipping_params;

        $result = Mage::helper('payexautopay/api')->getPx()->AddOrderAddress2($params);
        Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.AddOrderAddress2');
        return true;
    }
}