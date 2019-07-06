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
            Mage::helper('payex/tools')->addToDebug(
                sprintf('Transaction %s already processed.', $fields['transactionNumber']), $order->getIncrementId()
            );
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
     * @param Mage_Sales_Model_Order $order
     * @param bool $online
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function makeInvoice(&$order, $online = false)
    {
        if ($order->getGrandTotal() < 0) {
            Mage::throwException('Invalid order amount');
        }

        // Prepare Invoice
        /** @var Mage_Sales_Model_Order_Invoice $invoice */
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->addComment(Mage::helper('payex')->__('Auto-generated from PayEx module'), false, false);
        $invoice->setRequestedCaptureCase(
            $online ? Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE : Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE
        );
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

        $invoice->setIsPaid(true);

        $order->setTotalPaid($order->getTotalDue());
        $order->setBaseTotalPaid($order->getBaseTotalDue());
        $order->setTotalDue($order->getTotalDue() - $order->getTotalPaid());
        $order->getBaseTotalDue($order->getBaseTotalDue() - $order->getBaseTotalPaid());
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
        $order->save();

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

        $lines = $this->getOrderItems($order);
        foreach ($lines as $line) {
            $amount += (int)(100 * $line['price_with_tax']);
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
            $ShoppingCartItem->appendChild($dom->createElement('Description', htmlentities($item->getName())));
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
        $lines = $this->getOrderItems($order);

        // Replace illegal characters of product names
        $replace_illegal = $order->getPayment()->getMethodInstance()
            ->getConfigData('replace_illegal', $order->getStoreId());
        if ($replace_illegal) {
            $replacement_char = $order->getPayment()->getMethodInstance()
                ->getConfigData('replacement_char', $order->getStoreId());
            if (empty($replacement_char)) {
                $replacement_char = '-';
            }

            $lines = array_map(
                function ($value) use ($replacement_char) {
                if (isset($value['name'])) {
                    mb_regex_encoding('utf-8');
                    $value['name'] = mb_ereg_replace(
                        '[^a-zA-Z0-9_:!#=?\[\]@{}´ %-\/À-ÖØ-öø-ú]',
                        $replacement_char,
                        $value['name']
                    );
                }

                return $value;
                }, $lines
            );
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $OnlineInvoice = $dom->createElement('OnlineInvoice');
        $dom->appendChild($OnlineInvoice);
        $OnlineInvoice->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $OnlineInvoice->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsd',
            'http://www.w3.org/2001/XMLSchema'
        );

        $OrderLines = $dom->createElement('OrderLines');
        $OnlineInvoice->appendChild($OrderLines);

        // Add Order Lines
        foreach ($lines as $line) {
            $OrderLine = $dom->createElement('OrderLine');
            $OrderLine->appendChild($dom->createElement('Product', htmlentities($line['name'])));
            $OrderLine->appendChild($dom->createElement('Qty', $line['qty']));
            $OrderLine->appendChild($dom->createElement('UnitPrice', sprintf("%.2f", $line['price_without_tax'] / $line['qty'])));
            $OrderLine->appendChild($dom->createElement('VatRate', sprintf("%.2f", round($line['tax_percent']))));
            $OrderLine->appendChild($dom->createElement('VatAmount', sprintf("%.2f", $line['tax_price'])));
            $OrderLine->appendChild($dom->createElement('Amount', sprintf("%.2f", $line['price_with_tax'])));
            $OrderLines->appendChild($OrderLine);
        }

        return str_replace("\n", '', html_entity_decode(str_replace('xsi:xsd', 'xmlns:xsd', $dom->saveXML()), ENT_COMPAT|ENT_XHTML, 'UTF-8'));
    }

    /**
     * Get Assigned Status
     * @param $status
     * @return Mage_Sales_Model_Order_Status
     */
    public function getAssignedStatus($status) 
    {
        $status = Mage::getModel('sales/order_status')
            ->getCollection()
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status)
            ->getFirstItem();
        return $status;
    }

    /**
     * Get Invoice URL for Financing Invoice transaction
     * @param string|int $transaction_id
     * @return array
     */
    public function getInvoiceLink($transaction_id) 
    {
        // Call PxOrder.InvoiceLinkGet
        $params = array (
            'accountNumber' => '',
            'transactionNumber' => $transaction_id
        );
        $result = Mage::helper('payex/api')->getPx()->InvoiceLinkGet($params);
        return $result;
    }

    /**
     * Extract Credit Card Details
     * @param array $transaction
     * @return Varien_Object
     */
    public function getCCDetails(array $transaction)
    {
        // Get Masked Credit Card Number
        $masked_number = Mage::helper('payex')->__('Untitled Credit Card');
        if (!empty($transaction['maskedNumber'])) {
            $masked_number = $transaction['maskedNumber'];
        } elseif (!empty($transaction['maskedCard'])) {
            $masked_number = $transaction['maskedCard'];
        }

        // Get Card Type
        $card_type = '';
        if (!empty($transaction['cardProduct'])) {
            $card_type = $transaction['cardProduct'];
        } elseif (!empty($transaction['paymentMethod'])) {
            $card_type = $transaction['paymentMethod'];
        }

        /**
         * Card types: VISA, MC (Mastercard), EUROCARD, MAESTRO, DINERS (Diners Club), AMEX (American Express), LIC,
         * FDM, FORBRUGSFORENINGEN, JCB, FINAX, DANKORT
         */
        $card_type = strtolower(preg_replace('/[^A-Z]+/', '', $card_type));
        $card_type = str_replace('mc', 'mastercard', $card_type);
        if (empty($card_type)) {
            $card_type = 'visa';
        }

        // Get Expired
        $expire_date = '';
        if (!empty($transaction['paymentMethodExpireDate'])) {
            $expire_date = $transaction['paymentMethodExpireDate'];
        }

        $return = new Varien_Object();
        return $return->setMaskedNumber($masked_number)
            ->setType($card_type)
            ->setExpireDate($expire_date);
    }

    /**
     * Get Formatted CC name
     * Pattern: TYPE MASKED_NUMBER YYYY/MM
     * @param array $transaction
     * @return string
     */
    public function getFormattedCC(array $transaction)
    {
        $details = $this->getCCDetails($transaction);
        return sprintf(
            '%s %s %s', strtoupper(
                $details->getType()
            ),
            $details->getMaskedNumber(),
            Mage::getSingleton('core/date')->date('Y/m', strtotime($details->getExpireDate()))
        );
    }

    /**
     * Get Order Items
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getOrderItems($order)
    {
        $lines = array();
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            $itemQty = (int)$item->getQtyOrdered();
            $priceWithTax = $item->getRowTotalInclTax();
            $priceWithoutTax = $item->getRowTotal();
            $taxPercent = $priceWithoutTax > 0 ? (($priceWithTax / $priceWithoutTax) - 1) * 100 : 0;
            $taxPrice = $priceWithTax - $priceWithoutTax;
            $lines[] = array(
                'type' => 'product',
                'name' => $item->getName(),
                'qty' => $itemQty,
                'price_with_tax' => $priceWithTax,
                'price_without_tax' => $priceWithoutTax,
                'tax_price' => $taxPrice,
                'tax_percent' => $taxPercent
            );
        }

        // add Shipping
        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingIncTax = $order->getShippingInclTax();
            $shippingTax = $shippingIncTax - $shippingExclTax;
            $shippingTaxRate = $shippingExclTax > 0 ? (($shippingIncTax / $shippingExclTax) - 1) * 100 : 0;
            $lines[] = array(
                'type' => 'shipping',
                'name' => $order->getShippingDescription(),
                'qty' => 1,
                'price_with_tax' => $shippingIncTax,
                'price_without_tax' => $shippingExclTax,
                'tax_price' => $shippingTax,
                'tax_percent' => $shippingTaxRate
            );
        }

        // add Discount
        $discountData = Mage::helper('payex/discount')->getOrderDiscountData($order);
        if (abs($discountData->getDiscountInclTax()) > 0) {
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountVatAmount = $discountInclTax - $discountExclTax;
            $discountVatPercent = round((($discountInclTax / $discountExclTax) - 1) * 100);
            $lines[] = array(
                'type' => 'discount',
                'name' => Mage::helper('sales')->__('Discount (%s)', $order->getDiscountDescription()),
                'qty' => 1,
                'price_with_tax' => -1 * $discountInclTax,
                'price_without_tax' => -1 * $discountExclTax,
                'tax_price' => -1 * $discountVatAmount,
                'tax_percent' => $discountVatPercent
            );
        }

        // Add reward points
        if ((float)$order->getBaseRewardCurrencyAmount() > 0) {
            $lines[] = array(
                'type' => 'reward_points',
                'name' => Mage::helper('payex')->__('Reward points'),
                'qty' => 1,
                'price_with_tax' => -1 * $order->getBaseRewardCurrencyAmount(),
                'price_without_tax' => -1 * $order->getBaseRewardCurrencyAmount(),
                'tax_price' => 0,
                'tax_percent' => 0
            );
        }

        // add Payment Fee
        if ($order->getPayexPaymentFee() > 0 &&
            in_array(
                $order->getPayment()->getMethod(), array(
                'payex_financing',
                'payex_partpayment',
                'payex_invoice'
                )
            )) {
            $feeExclTax = $order->getPayexPaymentFee();
            $feeTax = $order->getPayexPaymentFeeTax();
            $feeIncTax = $feeExclTax + $feeTax;
            $feeTaxRate = $feeExclTax > 0 ? (($feeIncTax / $feeExclTax) - 1) * 100 : 0;

            $lines[] = array(
                'type' => 'fee',
                'name' => Mage::helper('payex')->__('Payment Fee'),
                'qty' => 1,
                'price_with_tax' => $feeIncTax,
                'price_without_tax' => $feeExclTax,
                'tax_price' => $feeTax,
                'tax_percent' => $feeTaxRate
            );
        }

        return $lines;
    }

    /**
     * Prepare Address Info
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getAddressInfo($order)
    {
        $billingAddress = $order->getBillingAddress()->getStreet();
        $billingCountryCode = $order->getBillingAddress()->getCountry();
        $billingCountry = Mage::getModel('directory/country')->load($billingCountryCode)->getName();

        $params = array(
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

        // add Shipping
        if (!$order->getIsVirtual()) {
            $deliveryAddress = $order->getShippingAddress()->getStreet();
            $deliveryCountryCode = $order->getShippingAddress()->getCountry();
            $deliveryCountry = Mage::getModel('directory/country')->load($deliveryCountryCode)->getName();

            $params = array_merge(
                $params, array(
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
                )
            );
        }

        return $params;
    }
}
