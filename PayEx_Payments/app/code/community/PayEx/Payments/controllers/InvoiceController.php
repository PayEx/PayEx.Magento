<?php

class PayEx_Payments_InvoiceController extends Mage_Core_Controller_Front_Action
{
    public function _construct()
    {
        // Bootstrap PayEx Environment
        Mage::getSingleton('payex/payment_invoice');
    }

    public function redirectAction()
    {
        Mage::helper('payex/tools')->addToDebug('Controller: redirect');

        // Load Order
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        /** @var PayEx_Payments_Model_Payment_Abstract $method */
        $method = $order->getPayment()->getMethodInstance();

        // Set quote to inactive
        Mage::getSingleton('checkout/session')->setPayexQuoteId(Mage::getSingleton('checkout/session')->getQuoteId());
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = ($method->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get CustomerId
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';

        // Get Additional Values
        $additional = $method->getConfigData('additionalValues') . '&INVOICE_MEDIADISTRIBUTION=11';

        // Responsive Skinning
        if ($method->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';
            $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payex/order')->getCalculatedOrderAmount($order)->getAmount();

        $ssn = Mage::getSingleton('checkout/session')->getSocialSecurtyNumber();
        $credit_data = Mage::getSingleton('checkout/session')->getCreditData();

        // Call PxOrder.Initialize8
        $params = array(
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $customer_id,
            'description' => Mage::app()->getStore()->getName(),
            'clientIPAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . Mage::helper('core/http')->getHttpUserAgent(),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => Mage::getUrl('payex/invoice/success', array('_secure' => true)),
            'view' => 'INVOICE',
            'agreementRef' => '',
            'cancelUrl' => Mage::getUrl('payex/invoice/cancel', array('_secure' => true)),
            'clientLanguage' => $method->getConfigData('clientlanguage')
        );
        $result = Mage::helper('payex/api')->getPx()->Initialize8($params);
        Mage::helper('payex/tools')->addToDebug('PxOrder.Initialize8:' . $result['description']);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);

            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment($message, Mage_Sales_Model_Order::STATE_CANCELED);
            $order->save();

            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            Mage::getSingleton('checkout/session')->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }
        $order_ref = $result['orderRef'];

        // Add Order Lines and Orders Address
        Mage::helper('payex/order')->addOrderLine($order_ref, $order);
        Mage::helper('payex/order')->addOrderAddress($order_ref, $order);

        $credit_data['full_name'] = $credit_data['firstName'] . ' ' . $credit_data['lastName'];

        // Limit strings
        $limit_rules = array(
            'full_name' => 35,
            'address' => 35,
            'postCode' => 9,
            'city' > 27
        );
        foreach ($credit_data as $key => $value) {
            if (isset($limit_rules[$key])) {
                if (mb_strlen($credit_data[$key], 'UTF-8') > $limit_rules[$key]) {
                    $credit_data[$key] = mb_substr($credit_data[$key], 0, $limit_rules[$key], 'UTF-8');
                }
            }
        }

        // Call Invoice Purchase
        $result = array();
        $px_method = Mage::getSingleton('checkout/session')->getMethod();
        switch ($px_method)
        {
            case 'private':
                // Call PxOrder.PurchaseInvoicePrivate
                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'customerRef' => $customer_id,
                    'customerName' => $credit_data['full_name'],
                    'streetAddress' => $credit_data['address'],
                    'coAddress' => '',
                    'postalCode' => $credit_data['postCode'],
                    'city' => $credit_data['city'],
                    'country' => $order->getBillingAddress()->getCountry(),
                    'socialSecurityNumber' => $ssn,
                    'phoneNumber' => '',
                    'email' => $order->getBillingAddress()->getEmail(),
                    'productCode' => '0001',
                    'creditcheckRef' => '',
                    'mediaDistribution' => $method->getConfigData('distribution'),
                    'invoiceText' => $method->getConfigData('invoicetext'),
                    'invoiceDate' => date('Y-m-d'),
                    'invoiceDueDays' => $method->getConfigData('invoiceduedays'),
                    'invoiceNumber' => $order_id,
                    'invoiceLayout' => ''
                );
                $result = Mage::helper('payex/api')->getPx()->PurchaseInvoicePrivate($params);
                Mage::helper('payex/tools')->addToDebug('PxOrder.PurchaseInvoicePrivate:' . $result['description']);
                break;
            case 'corporate':
                // Call PxOrder.PurchaseInvoiceCorporate
                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'companyRef' => 'C' . $customer_id,
                    'companyName' => $credit_data['name'], // Firm name
                    'streetAddress' => $credit_data['address'],
                    'coAddress' => $credit_data['address'],
                    'postalCode' => $credit_data['postCode'],
                    'city' => $credit_data['city'],
                    'country' => $order->getBillingAddress()->getCountry(),
                    'organizationNumber' => $ssn,
                    'phoneNumber' => '',
                    'email' => $order->getBillingAddress()->getEmail(),
                    'productCode' => '0001',
                    'creditcheckRef' => $method->getConfigData('unapproved') ? '' : $credit_data['creditCheckRef'],
                    'mediaDistribution' => $method->getConfigData('distribution'),
                    'invoiceText' => $method->getConfigData('invoicetext'),
                    'invoiceDate' => date('Y-m-d'),
                    'invoiceDueDays' => $method->getConfigData('invoiceduedays'),
                    'invoiceNumber' => $order_id,
                    'invoiceLayout' => ''
                );
                $result = Mage::helper('payex/api')->getPx()->PurchaseInvoiceCorporate($params);
                Mage::helper('payex/tools')->addToDebug('PxOrder.PurchaseInvoiceCorporate:' . $result['description']);
                break;
            default:
                Mage::throwException('Unknown payment method for invoice');
        }

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);

            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment($message, Mage_Sales_Model_Order::STATE_CANCELED);
            $order->save();

            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            Mage::getSingleton('checkout/session')->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // Prevent Order cancellation when used TC
        if (in_array((int)$result['transactionStatus'], array(0, 3, 6)) && $order->getState() === Mage_Sales_Model_Order::STATE_CANCELED) {
            if ($order->getState() === Mage_Sales_Model_Order::STATE_CANCELED) {
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->save();

                foreach ($order->getAllItems() as $item) {
                    $item->setQtyCanceled(0);
                    $item->save();
                }
            }

            Mage::helper('payex/tools')->addToDebug('Order has been uncanceled.', $order_id);
        }

        // Process Transaction
        Mage::helper('payex/tools')->addToDebug('Process Payment Transaction...', $order_id);
        $transaction = Mage::helper('payex/order')->processPaymentTransaction($order, $result);
        $transaction_status = isset($result['transactionStatus']) ? (int)$result['transactionStatus'] : null;

        // Check Order and Transaction Result
        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 0;
            case 1;
            case 3;
            case 6:
                // Select Order Status
                if (in_array($transaction_status, array(0, 6))) {
                    $new_status = $method->getConfigData('order_status_capture');
                } elseif ($transaction_status === 3 || (isset($result['pending']) && $result['pending'] === 'true')) {
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

                // Create Invoice for Sale Transaction
                if (in_array($transaction_status, array(0, 6))) {
                    $invoice = Mage::helper('payex/order')->makeInvoice($order, false);
                    $invoice->setTransactionId($result['transactionNumber']);
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

                // Redirect to Success Page
                Mage::getSingleton('checkout/session')->setLastSuccessQuoteId(Mage::getSingleton('checkout/session')->getPayexQuoteId());
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
                break;
            default:
                // Cancel order
                if ($transaction->getIsCancel()) {
                    Mage::helper('payex/tools')->addToDebug('Cancel: ' . $transaction->getMessage(), $order->getIncrementId());

                    $order->cancel();
                    $order->addStatusHistoryComment($transaction->getMessage());
                    $order->save();
                    $order->sendOrderUpdateEmail(true, $transaction->getMessage());
                }

                // Set quote to active
                if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
                    $quote = Mage::getModel('sales/quote')->load($quoteId);
                    if ($quote->getId()) {
                        $quote->setIsActive(true)->save();
                        Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                    }
                }

                Mage::getSingleton('checkout/session')->addError($transaction->getMessage());
                $this->_redirect('checkout/cart');
        }
    }
}
