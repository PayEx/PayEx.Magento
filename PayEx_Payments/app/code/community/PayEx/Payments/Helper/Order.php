<?php

class PayEx_Payments_Helper_Order extends Mage_Core_Helper_Abstract
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
	        Mage::helper('payex/tools')->addToDebug(sprintf('Transaction %s already processed.', $fields['transactionNumber']), $order->getIncrementId());
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
                    $message = Mage::helper('payex')->__('Transaction Status: %s.', $transaction_status);
                    $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, true, $message);
                    $transaction->setIsClosed(0);
                    $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                    $transaction->setMessage($message);
	                $transaction->save();
	                break;
                }

	            $message = Mage::helper('payex')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
                $transaction->save();
                break;
            case 3:
                $message = Mage::helper('payex')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, true, $message);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
	            $transaction->save();
                break;
            case 0;
            case 6:
                $message = Mage::helper('payex')->__('Transaction Status: %s.', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setMessage($message);
	            $transaction->save();
                break;
            case 2:
                $message = Mage::helper('payex')->__('Detected an abnormal payment process (Transaction Status: %s).', $transaction_status);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true, $message);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
	            $transaction->setIsCancel(true);
                $transaction->save();
                break;
            case 4;
                $message = Mage::helper('payex')->__('Order automatically canceled. Transaction is canceled.');
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
	            $transaction->setIsCancel(true);
                $transaction->save();
                break;
            case 5;
                $message = Mage::helper('payex')->__('Order automatically canceled. Transaction is failed.');
                $message .= ' ' . Mage::helper('payex/tools')->getVerboseErrorMessage($fields);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $fields);
                $transaction->setMessage($message);
	            $transaction->setIsCancel(true);
                $transaction->save();
                break;
            default:
                $message = Mage::helper('payex')->__('Invalid transaction status.');
                $transaction = Mage::getModel('sales/order_payment_transaction');
                $transaction->setMessage($message);
                $transaction->setIsCancel(true);
                break;
        }

	    try {
		    $order->save();
		    Mage::helper('payex/tools')->addToDebug($message, $order->getIncrementId());
	    } catch (Exception $e) {
		    Mage::helper('payex/tools')->addToDebug('Error: ' . $e->getMessage(), $order->getIncrementId());
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
            $invoice_id = $invoice->create($order->getIncrementId(), $order->getAllItems(), Mage::helper('payex')->__('Auto-generated from PayEx module'), false, false);
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
            $invoice->addComment(Mage::helper('payex')->__('Auto-generated from PayEx module'), false, false);
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
        $creditmemo->addComment(Mage::helper('payex')->__('Auto-generated from PayEx module'));

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
     * Calculate Order amount
     * With rounding issue detection
     * @param Mage_Sales_Model_Order $order
     * @param int $order_amount
     * @return Varien_Object
     */
    public function getCalculatedOrderAmount($order, $order_amount = 0)
    {
        // Order amount calculated by shop
        if ($order_amount === 0) {
            $order_amount = $order->getGrandTotal();
        }

        // Order amount calculated manually
        $amount = 0;

        // add Order Items
        $items = $order->getAllVisibleItems();
        /** @var $item Mage_Sales_Model_Order_Item */
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $amount += (int)(100 * $item->getRowTotalInclTax());
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingIncTax = $order->getShippingInclTax();
            $amount += (int)(100 * $shippingIncTax);
        }

        // add Discount
        $discountData = Mage::helper('payex/discount')->getOrderDiscountData($order);
        $discountInclTax = (int) (100 * $discountData->getDiscountInclTax());
        $amount += -1 * $discountInclTax;

        // Add reward points
        $amount += -1 * (int)(100 * $order->getBaseRewardCurrencyAmount());

        // add Fee
        $fee = $order->getPayexPaymentFee() + $order->getPayexPaymentFeeTax();
        if ($fee > 0) {
            $amount += (int)(100 * $fee);
        }

        // Detect Rounding Issue
        $rounded_total = sprintf("%.2f", $order_amount);
        $rounded_control_amount = sprintf("%.2f", ($amount / 100));
        $rounding = 0;
        if ($rounded_total !== $rounded_control_amount) {
            if ($rounded_total > $rounded_control_amount) {
                $rounding = $rounded_total - $rounded_control_amount;
            } else {
                $rounding = -1 * ($rounded_control_amount - $rounded_total);
            }
            $rounding = sprintf("%.2f", $rounding);
        }

        $result = new Varien_Object();
        return $result->setAmount($rounded_control_amount)->setRounding($rounding);
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
            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getRowTotalInclTax();
            $priceWithoutTax = $item->getRowTotal();
            $taxPercent = (($priceWithTax / $priceWithoutTax) - 1) * 100; // works for all types
            $taxPrice = $priceWithTax - $priceWithoutTax;

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
                'amount' => (int)(100 * $priceWithTax), //must include tax
                'vatPrice' => (int)(100 * $taxPrice),
                'vatPercent' => (int)(100 * $taxPercent)
            );

            $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = (int) (100 * $order->getShippingAmount());
            $shippingIncTax = (int) (100 * $order->getShippingInclTax());
            $shippingTax = $shippingIncTax - $shippingExclTax;

            // find out tax-rate for the shipping
            if ((float) $shippingIncTax && (float) $shippingExclTax) {
                $shippingTaxRate = (($shippingIncTax / $shippingExclTax) - 1) * 100;
            } else {
                $shippingTaxRate = 0;
            }

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
                'amount' => (int)($shippingIncTax),
                'vatPrice' => (int)($shippingTax),
                'vatPercent' => (int)(100 * $shippingTaxRate)
            );

            $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // add Discount
        $discountData = Mage::helper('payex/discount')->getOrderDiscountData($order);
        $discountInclTax = (int) (100 * $discountData->getDiscountInclTax());
        $discountExclTax = (int) (100 * $discountData->getDiscountExclTax());
        $discountVatAmount = $discountInclTax - $discountExclTax;
        $discountVatPercent = (($discountInclTax / $discountExclTax) - 1) * 100;

        if (abs($discountInclTax) > 0) {
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
                'amount' => -1 * (int)($discountInclTax),
                'vatPrice' => -1 * (int) ($discountVatAmount),
                'vatPercent' => (int) (100 * $discountVatPercent)
            );

            $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // add Payment Fee
        if ((float)$order->getPayexPaymentFee() > 0) {
            $feeExclTax = $order->getPayexPaymentFee();
            $feeTax = $order->getPayexPaymentFeeTax();
            $feeIncTax = $feeExclTax + $feeTax;

            // find out tax-rate for the fee
            if ((float) $feeIncTax && (float) $feeExclTax) {
                $feeTaxRate = Mage::app()->getStore()->roundPrice((($feeIncTax / $feeExclTax) - 1) * 100);
            } else {
                $feeTaxRate = 0;
            }

            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => Mage::helper('payex')->__('Payment fee'),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => 1,
                'amount' => (int)(100 * $feeIncTax), //must include tax
                'vatPrice' => (int) (100 * $feeTax),
                'vatPercent' =>  (int) (100 * $feeTaxRate)
            );

            $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            $i++;
        }

        // Add reward points
        if ((float)$order->getBaseRewardCurrencyAmount() > 0) {
            $params = array(
                'accountNumber' => '',
                'orderRef' => $orderRef,
                'itemNumber' => $i,
                'itemDescription1' => Mage::helper('payex')->__('Reward points'),
                'itemDescription2' => '',
                'itemDescription3' => '',
                'itemDescription4' => '',
                'itemDescription5' => '',
                'quantity' => 1,
                'amount' => -1 * (int)(100 * $order->getBaseRewardCurrencyAmount()), //must include tax
                'vatPrice' => 0,
                'vatPercent' => 0
            );

            $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
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

        $result = Mage::helper('payex/api')->getPx()->AddOrderAddress2($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddOrderAddress2');
        return true;
    }

    /**
     * Get Shopping Cart XML for MasterPass
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $quote
     * @return string
     */
    public function getShoppingCartXML($quote)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        if ($quote instanceof Mage_Sales_Model_Order) {
            $currency = $quote->getOrderCurrencyCode();
        } else {
            $currency = $quote->getQuoteCurrencyCode();
        }

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $currency));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $quote->getGrandTotal())));

        // Add Order Lines
        $items = $quote->getAllVisibleItems();
        /** @var $item Mage_Sales_Model_Quote_Item */
        foreach ($items as $item) {
            $product = $item->getProduct();
            if ($quote instanceof Mage_Sales_Model_Order) {
                $qty = $item->getQtyOrdered();
            } else {
                $qty = $item->getQty();
            }

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (float)$qty));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $product->getThumbnailUrl()));
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Generate Invoice Print XML
     * (only used for Factoring & PartPayment)
     * @param Mage_Sales_Model_Order $order
     * @return mixed
     */
    public function getInvoiceExtraPrintBlocksXML($order)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $OnlineInvoice = $dom->createElement('OnlineInvoice');
        $dom->appendChild($OnlineInvoice);
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $OnlineInvoice->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsd', 'http://www.w3.org/2001/XMLSchema');

        $OrderLines = $dom->createElement('OrderLines');
        $OnlineInvoice->appendChild($OrderLines);

        // Add Order Lines
        $items = $order->getAllVisibleItems();
        /** @var $item Mage_Sales_Model_Order_Item */
        foreach ($items as $item) {
            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getRowTotalInclTax();
            $priceWithoutTax = $item->getRowTotal();
            $taxPercent = (($priceWithTax / $priceWithoutTax) - 1) * 100; // works for all types
            $taxPrice = $priceWithTax - $priceWithoutTax;

            mb_regex_encoding('utf-8');

            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', trim(mb_ereg_replace('[^a-zA-Z0-9_:!#=?\[\]@{}´ %-\/À-ÖØ-öø-ú]',"-",$item->getName()))));
            $OrderLine->appendChild($dom->createElement('Qty', $itemQty));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $priceWithoutTax / $itemQty)));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", $taxPercent)));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", $taxPrice)));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $priceWithTax)));
            $OrderLines->appendChild($OrderLine);
        }

        // Add Shipping Line
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingIncTax = $order->getShippingInclTax();
            $shippingTax = $shippingIncTax - $shippingExclTax;

            // find out tax-rate for the shipping
            if ((float) $shippingIncTax && (float) $shippingExclTax) {
                $shippingTaxRate = Mage::app()->getStore()->roundPrice((($shippingIncTax / $shippingExclTax) - 1) * 100);
            } else {
                $shippingTaxRate = 0;
            }

            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $order->getShippingDescription()));
            $OrderLine->appendChild($dom->createElement('Qty', 1));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $shippingExclTax)));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", $shippingTaxRate)));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", $shippingTax)));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $shippingIncTax)));
            $OrderLines->appendChild($OrderLine);
        }

        // add Payment Fee
        $fee = $order->getPayexPaymentFee();
        if ($fee > 0) {
            $feeExclTax = $order->getPayexPaymentFee();
            $feeTax = $order->getPayexPaymentFeeTax();
            $feeIncTax = $feeExclTax + $feeTax;

            // find out tax-rate for the fee
            if ((float) $feeIncTax && (float) $feeExclTax) {
                $feeTaxRate = Mage::app()->getStore()->roundPrice((($feeIncTax / $feeExclTax) - 1) * 100);
            } else {
                $feeTaxRate = 0;
            }

            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', Mage::helper('payex')->__('Payment fee')));
            $OrderLine->appendChild($dom->createElement('Qty', 1));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $feeExclTax)));
            $OrderLine->appendChild($dom->createElement('VatRate', $feeTaxRate));
            $OrderLine->appendChild($dom->createElement('VatAmount', $feeTax));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $feeIncTax)));
            $OrderLines->appendChild($OrderLine);
        }

        // add Discount
        $discountData = Mage::helper('payex/discount')->getOrderDiscountData($order);
        $discountInclTax = $discountData->getDiscountInclTax();
        $discountExclTax = $discountData->getDiscountExclTax();
        $discountVatAmount = $discountInclTax - $discountExclTax;
        $discountVatPercent = (($discountInclTax / $discountExclTax) - 1) * 100;

        if (abs($discountInclTax) > 0) {
            $discount_description = ($order->getDiscountDescription() !== null) ? Mage::helper('sales')->__('Discount (%s)', $order->getDiscountDescription()) : Mage::helper('sales')->__('Discount');

            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', $discount_description));
            $OrderLine->appendChild($dom->createElement('Qty', 1));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", -1 * $discountExclTax)));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", $discountVatPercent)));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", -1 * $discountVatAmount)));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", -1 * $discountInclTax)));
            $OrderLines->appendChild($OrderLine);
        }

        // Add reward points
        if ((float)$order->getBaseRewardCurrencyAmount() > 0) {
            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', Mage::helper('payex')->__('Reward points')));
            $OrderLine->appendChild($dom->createElement('Qty', 1));
            $OrderLine->appendChild($dom->createElement('UnitPrice', -1 * $order->getBaseRewardCurrencyAmount()));
            $OrderLine->appendChild($dom->createElement('VatRate', 0));
            $OrderLine->appendChild($dom->createElement('VatAmount', 0));
            $OrderLine->appendChild($dom->createElement('Amount', -1 * $order->getBaseRewardCurrencyAmount()));
            $OrderLines->appendChild($OrderLine);
        }

        return str_replace("\n", '', $dom->saveXML());
    }

    /**
     * Get Assigned Status
     * @param $status
     * @return Mage_Sales_Model_Order_Status
     */
    public function getAssignedStatus($status) {
        $status = Mage::getModel('sales/order_status')
            ->getCollection()
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status)
            ->getFirstItem();
        return $status;
    }
}