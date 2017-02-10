<?php

class PayEx_Payments_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function _construct()
    {
        // Bootstrap PayEx Environment
        Mage::getSingleton('payex/payment_CC');
    }

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
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

        // Set quote to inactive
        Mage::getSingleton('checkout/session')->setPayexQuoteId(Mage::getSingleton('checkout/session')->getQuoteId());
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();

        /** @var PayEx_Payments_Model_Payment_Abstract $method */
        $method = $order->getPayment()->getMethodInstance();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = ($method->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get Payment Type (PX, CREDITCARD etc)
        $paymentview = $method->getConfigData('paymentview');

        // Get Additional Values
        $additional = ($paymentview === 'PX' ? 'PAYMENTMENU=TRUE' : '');

        // Direct Debit uses 'SALE' only
        if ($paymentview === 'DIRECTDEBIT') {
            $operation = 'SALE';
        }

        // Responsive Skinning
        if ($method->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';

            // PayEx Payment Page 2.0  works only for View 'Credit Card' and 'Direct Debit' at the moment
            if (in_array($paymentview, array('CREDITCARD', 'DIRECTDEBIT'))) {
                $additional .= $separator . 'RESPONSIVE=1';
            } else {
                $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
            }
        }

        // Get Agreement Reference
        $agreement = '';
        $isBARequested = (bool)$order->getPayment()
            ->getAdditionalInformation(PayEx_Payments_Model_Payment_Agreement::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);
        if ($isBARequested) {
            $agreement = (string)$order->getPayment()
                ->getAdditionalInformation(PayEx_Payments_Model_Payment_Agreement::PAYMENT_INFO_TRANSPORT_AGREEMENT_REFERENCE);
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payex/order')->getCalculatedOrderAmount($order)->getAmount();

        // Call PxOrder.Initialize8
        $params = array(
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($amount * 100),
            'priceArgList' => '',
            'currency' => $currency_code,
            'vat' => 0,
            'orderID' => $order_id,
            'productNumber' => $order_id,
            'description' => Mage::app()->getStore()->getName(),
            'clientIPAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . Mage::helper('core/http')->getHttpUserAgent(),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => Mage::getUrl('payex/payment/success', array('_secure' => true)),
            'view' => $paymentview,
            'agreementRef' => $agreement,
            'cancelUrl' => Mage::getUrl('payex/payment/cancel', array('_secure' => true)),
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
        $redirectUrl = $result['redirectUrl'];

        // Add Order Lines and Orders Address
        if ($method->getConfigData('checkoutinfo')) {
            // Add Order Items
            $items = Mage::helper('payex/order')->getOrderItems($order);
            foreach ($items as $index => $item) {
                // Call PxOrder.AddSingleOrderLine2
                $params = array(
                    'accountNumber' => '',
                    'orderRef' => $order_ref,
                    'itemNumber' => ($index + 1),
                    'itemDescription1' => $item['name'],
                    'itemDescription2' => '',
                    'itemDescription3' => '',
                    'itemDescription4' => '',
                    'itemDescription5' => '',
                    'quantity' => $item['qty'],
                    'amount' => (int)(100 * $item['price_with_tax']), //must include tax
                    'vatPrice' => (int)(100 * $item['tax_price']),
                    'vatPercent' => (int)(100 * $item['tax_percent'])
                );

                $result = Mage::helper('payex/api')->getPx()->AddSingleOrderLine2($params);
                Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddSingleOrderLine2');
            }

            // Add Order Address Info
            $params = array_merge(
                array(
                'accountNumber' => '',
                'orderRef' => $order_ref
                ), Mage::helper('payex/order')->getAddressInfo($order)
            );

            $result = Mage::helper('payex/api')->getPx()->AddOrderAddress2($params);
            Mage::helper('payex/tools')->debugApi($result, 'PxOrder.AddOrderAddress2');
        }

        // Set Pending Payment status
        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('payex')->__('The customer was redirected to PayEx.'));
        $order->save();

        // Redirect to PayEx
        Mage::app()->getFrontController()->getResponse()->setRedirect($redirectUrl)->sendResponse();
    }

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function successAction()
    {
        Mage::helper('payex/tools')->addToDebug('Controller: success');

        // Check OrderRef
        $orderRef = $this->getRequest()->getParam('orderRef');
        if (empty($orderRef)) {
            $this->_redirect('checkout/cart');
            return;
        }

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

        // Call PxOrder.Complete
        $params = array(
            'accountNumber' => '',
            'orderRef' => $orderRef
        );
        $result = Mage::helper('payex/api')->getPx()->Complete($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxOrder.Complete');
        if ($result['errorCodeSimple'] !== 'OK') {
            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment(Mage::helper('payex')->__('Order automatically canceled. Failed to complete payment.'));
            $order->save();

            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
            Mage::getSingleton('checkout/session')->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // Check Transaction is already registered
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
            ->addAttributeToFilter('txn_id', $result['transactionNumber']);
        if (count($collection) > 0) {
            $transaction = $collection->getFirstItem();
            $raw_details_info = $transaction->getAdditionalInformation('raw_details_info');
            if (is_array($raw_details_info) && in_array((int)$result['transactionStatus'], array(0, 3, 6))) {
                // Redirect to Success Page
                Mage::helper('payex/tools')->addToDebug('Redirected to success page because transaction is already paid.', $order_id);
                Mage::getSingleton('checkout/session')->setLastSuccessQuoteId(Mage::getSingleton('checkout/session')->getPayexQuoteId());
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
                return;
            }
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

        // Save Agreement Reference
        if (!empty($result['agreementRef'])) {
            /** @var Mage_Sales_Model_Billing_Agreement $billing_agreement */
            $billing_agreement = Mage::getModel('sales/billing_agreement')->load($result['agreementRef'], 'reference_id');
            if ($billing_agreement->getId()) {
                // Verify Agreement Reference
                // Call PxAgreement.AgreementCheck
                $params = array(
                    'accountNumber' => '',
                    'agreementRef' => $result['agreementRef'],
                );
                $check = Mage::helper('payex/api')->getPx()->AgreementCheck($params);
                Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.AgreementCheck');
                if ($check['code'] === 'OK' && $check['description'] === 'OK' && $check['errorCode'] === 'OK') {
                    $agreement_status = (int)$check['agreementStatus'];

                    // Check Agreement Status
                    switch ($agreement_status) {
                        case (PayEx_Payments_Model_Payment_Agreement::AGREEMENT_VERIFIED):
                            // Update Billing Agreement
                            $masked_number = Mage::helper('payex/order')->getFormattedCC($result);
                            $billing_agreement->setAgreementLabel($masked_number)->save();
                            break;
                        case (PayEx_Payments_Model_Payment_Agreement::AGREEMENT_NOTVERIFIED):
                        case (PayEx_Payments_Model_Payment_Agreement::AGREEMENT_DELETED):
                        case (PayEx_Payments_Model_Payment_Agreement::AGREEMENT_NOTEXISTS):
                            // Remove Billing Agreement
                            $billing_agreement->delete();
                            break;
                        default:
                            // no break
                    }
                }
            }
        }

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

    /**
     * @throws Exception
     */
    public function cancelAction()
    {
        Mage::helper('payex/tools')->addToDebug('Controller: cancel');

        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);

        // Note: Cancel only non-captured orders!
        if (!$order->isCanceled() && !$order->hasInvoices()) {
            // Set Canceled State
            $order->cancel();
            $order->addStatusHistoryComment(Mage::helper('payex')->__('Order canceled by user'), Mage_Sales_Model_Order::STATE_CANCELED);
            $order->save();
        }

        // Set quote to active
        if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
            }
        }

        Mage::getSingleton('checkout/session')->addError(Mage::helper('payex')->__('Order canceled by user'));
        $this->_redirect('checkout/cart');
    }
}