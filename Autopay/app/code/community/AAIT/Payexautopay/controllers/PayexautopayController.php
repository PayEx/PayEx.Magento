<?php

/**
 * PayEx Autopay Controller
 * Created by AAIT Team.
 */
class AAIT_Payexautopay_PayexautopayController extends Mage_Core_Controller_Front_Action
{
    public function _construct()
    {
        Mage::getSingleton('payexautopay/payment');
    }

    public function autopayAction()
    {
        Mage::helper('payexautopay/tools')->addToDebug('Controller: autopay');

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

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = (Mage::getSingleton('payexautopay/payment')->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get CustomerId
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payexautopay/order')->getCalculatedOrderAmount($order)->amount;

        // Get Current Customer Agreement
        $agreement = Mage::getModel('payexautopay/agreement')->load($customer_id, 'customer_id');
        $agreement_status = AAIT_Payexautopay_Model_Payment::AGREEMENT_NOTEXISTS;

        // Get Agreement Status
        if ($agreement->getId()) {
            // Call PxAgreement.AgreementCheck
            $params = array(
                'accountNumber' => '',
                'agreementRef' => $agreement->getAgreementRef(),
            );
            $result = Mage::helper('payexautopay/api')->getPx()->AgreementCheck($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxAgreement.AgreementCheck');

            // Check Errors
            if ($result['code'] !== 'OK' && $result['description'] !== 'OK') {
                $message = Mage::helper('payexautopay/tools')->getVerboseErrorMessage($result);

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

            $agreement_status = (int)$result['agreementStatus'];
            Mage::helper('payexautopay/tools')->addToDebug('PxAgreement.AgreementCheck Status is ' . $agreement_status . ' (NotVerified = 0, Verified = 1, Deleted = 2)');
            Mage::helper('payexautopay/tools')->addToDebug('Reserved Order for CustomerId #' . $customer_id, $order_id);
            Mage::helper('payexautopay/tools')->addToDebug('Current Agreement Status for CustomerId #' . $customer_id . ' is ' . var_export($agreement_status, true));
        }

        // Check Agreement Status
        switch ($agreement_status) {
            case (AAIT_Payexautopay_Model_Payment::AGREEMENT_DELETED):
                // Remove Deleted Agreement ID
                $agreement->delete();
            case (AAIT_Payexautopay_Model_Payment::AGREEMENT_NOTEXISTS):
                // Create Agreement ID
                // Call PxAgreement.CreateAgreement3
                $params = array(
                    'accountNumber' => '',
                    'merchantRef' => Mage::getSingleton('payexautopay/payment')->getConfigData('agreementurl'),
                    'description' => Mage::app()->getStore()->getName(),
                    'purchaseOperation' => $operation,
                    'maxAmount' => round(Mage::getSingleton('payexautopay/payment')->getConfigData('maxamount') * 100),
                    'notifyUrl' => '',
                    'startDate' => '',
                    'stopDate' => ''
                );
                $result = Mage::helper('payexautopay/api')->getPx()->CreateAgreement3($params);
                Mage::helper('payexautopay/tools')->debugApi($result, 'PxAgreement.CreateAgreement3');
                if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
                    $message = Mage::helper('payexautopay/tools')->getVerboseErrorMessage($result);

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

                // Save Customer Agreement ID
                $model = Mage::getModel('payexautopay/agreement');
                $model->setCustomerId($customer_id)
                    ->setAgreementRef($result['agreementRef'])
                    ->setCreatedAt(date('Y-m-d H:i:s', time()))
                    ->save();

                Mage::helper('payexautopay/tools')->addToDebug('Agreement for CustomerId #' . $customer_id . ' created');
            case (AAIT_Payexautopay_Model_Payment::AGREEMENT_VERIFIED):
                // Call PxAgreement.AutoPay3
                $params = array(
                    'accountNumber' => '',
                    'agreementRef' => $agreement->getAgreementRef(),
                    'price' => round($amount * 100),
                    'productNumber' => (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0',
                    'description' => Mage::app()->getStore()->getName(),
                    'orderId' => $order_id,
                    'purchaseOperation' => $operation,
                    'currency' => $currency_code
                );
                $result = Mage::helper('payexautopay/api')->getPx()->AutoPay3($params);
                Mage::helper('payexautopay/tools')->debugApi($result, 'PxAgreement.AutoPay3');

                // Check errors
                if ($result['errorCodeSimple'] !== 'OK') {
                    // AutoPay: NOT successful
                    // Reset Customer Agreement
                    Mage::helper('payexautopay/tools')->addToDebug('Warning: AgreementId ' . $agreement->getAgreementRef() . ' of CustomerId ' . $customer_id . ' is removed!');
                    $agreement->delete();

                    // Try to pay again using PayEx Credit Card
                    $redirectUrl = Mage::getUrl('payexautopay/payexautopay/redirect', array('_secure' => true));
                    $this->_redirectUrl($redirectUrl);
                    return;
                }

                // Validate transactionStatus value
                if (empty($result['transactionStatus']) || !is_numeric($result['transactionStatus'])) {
                    // AutoPay: No transactionsStatus in response
                    Mage::helper('payexautopay/tools')->addToDebug('Error: No transactionsStatus in response.', $order->getIncrementId());

                    // Reset Customer Agreement
                    Mage::helper('payexautopay/tools')->addToDebug('Warning: AgreementId ' . $agreement->getAgreementRef() . ' of CustomerId ' . $customer_id . ' is removed!');
                    $agreement->delete();

                    // Try to pay again using PayEx Credit Card
                    $redirectUrl = Mage::getUrl('payexautopay/payexautopay/redirect', array('_secure' => true));
                    $this->_redirectUrl($redirectUrl);
                    return;
                }

                // Redirect to Success Action
                Mage::getSingleton('checkout/session')->setTransaction($result);
                $redirectUrl = Mage::getUrl('payexautopay/payexautopay/success', array('_secure' => true));
                $this->_redirectUrl($redirectUrl);
                return;
        }

        // Show Error
        $message = Mage::helper('payexautopay')->__('Failed to process order');

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
    }

    public function redirectAction()
    {
        Mage::helper('payexautopay/tools')->addToDebug('Controller: redirect');

        // Load Order
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = (Mage::getSingleton('payexautopay/payment')->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get CustomerId
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';

        // Get Additional Values
        $additional = '';

        // Responsive Skinning
        if (Mage::getSingleton('payexautopay/payment')->getConfigData('responsive') === '1') {
            $separator = (!empty($additional) && mb_substr($additional, -1) !== '&') ? '&' : '';
            $additional .= $separator . 'USECSS=RESPONSIVEDESIGN';
        }

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payexautopay/order')->getCalculatedOrderAmount($order)->amount;

        // Get Current Customer Agreement
        $agreement = Mage::getModel('payexautopay/agreement')->load($customer_id, 'customer_id');

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
            'returnUrl' => Mage::getUrl('payexautopay/payexautopay/success', array('_secure' => true)),
            'view' => 'CREDITCARD',
            'agreementRef' => $agreement->getAgreementRef(),
            'cancelUrl' => Mage::getUrl('payexautopay/payexautopay/cancel', array('_secure' => true)),
            'clientLanguage' => Mage::getSingleton('payexautopay/payment')->getConfigData('clientlanguage')
        );
        $result = Mage::helper('payexautopay/api')->getPx()->Initialize8($params);
        Mage::helper('payexautopay/tools')->addToDebug('PxOrder.Initialize8:' . $result['description']);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
            $message = Mage::helper('payexautopay/tools')->getVerboseErrorMessage($result);

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
        Mage::helper('payexautopay/order')->addOrderLine($order_ref, $order);
        Mage::helper('payexautopay/order')->addOrderAddress($order_ref, $order);

        // Set Pending Payment status
        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('payexautopay')->__('The customer was redirected to PayEx.'));
        $order->save();

        // Redirect to PayEx
        header('Location: ' . $redirectUrl);
        exit();
    }

    public function successAction()
    {
        Mage::helper('payexautopay/tools')->addToDebug('Controller: success');

        // Load Order
        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        $result = Mage::getSingleton('checkout/session')->getTransaction();
        if (!$result) {
            // Check OrderRef
            if (empty($_GET['orderRef'])) {
                $this->_redirect('checkout/cart');
                return;
            }

            // Call PxOrder.Complete
            $params = array(
                'accountNumber' => '',
                'orderRef' => $_GET['orderRef']
            );
            $result = Mage::helper('payexautopay/api')->getPx()->Complete($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxOrder.Complete');
            if ($result['errorCodeSimple'] !== 'OK') {
                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment(Mage::helper('payexautopay')->__('Order automatically canceled. Failed to complete payment.'));
                $order->save();

                // Set quote to active
                if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
                    $quote = Mage::getModel('sales/quote')->load($quoteId);
                    if ($quote->getId()) {
                        $quote->setIsActive(true)->save();
                        Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                    }
                }

                $message = Mage::helper('payexautopay/tools')->getVerboseErrorMessage($result);
                Mage::getSingleton('checkout/session')->addError($message);
                $this->_redirect('checkout/cart');
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

            Mage::helper('payexautopay/tools')->addToDebug('Order has been uncanceled.', $order_id);
        }

        // Process Transaction
        Mage::helper('payexautopay/tools')->addToDebug('Process Payment Transaction...', $order_id);
        $transaction = Mage::helper('payexautopay/order')->processPaymentTransaction($order, $result);
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
                    $new_status = Mage::getSingleton('payexautopay/payment')->getConfigData('order_status_capture');
                } elseif ($transaction_status === 3 || (isset($result['pending']) && $result['pending'] === 'true')) {
                    $new_status = Mage::getSingleton('payexautopay/payment')->getConfigData('order_status_authorize');
                } else {
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
                $order->addStatusHistoryComment(Mage::helper('payexautopay')->__('Order has been paid'), $new_status);

                // Create Invoice for Sale Transaction
                if (in_array($transaction_status, array(0, 6))) {
                    $invoice = Mage::helper('payexautopay/order')->makeInvoice($order, false);
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
                    Mage::helper('payexautopay/tools')->addToDebug('Cancel: ' . $transaction->getMessage(), $order->getIncrementId());

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

    public function cancelAction()
    {
        Mage::helper('payexautopay/tools')->addToDebug('Controller: cancel');

        $order_id = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);

        // Note: Cancel only non-captured orders!
        if (!$order->isCanceled() && !$order->hasInvoices()) {
            // Set Canceled State
            $order->cancel();
            $order->addStatusHistoryComment(Mage::helper('payexautopay')->__('Order canceled by user'), Mage_Sales_Model_Order::STATE_CANCELED);
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

        Mage::getSingleton('checkout/session')->addError(Mage::helper('payexautopay')->__('Order canceled by user'));
        $this->_redirect('checkout/cart');
    }

    public function cancel_agreementAction()
    {
        Mage::helper('payexautopay/tools')->addToDebug('Controller: cancel_agreement');

        // Get CustomerId
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';
        $agreement = Mage::getModel('payexautopay/agreement')->load($customer_id, 'customer_id');

        // Cancel Agreement
        if ($agreement->getId()) {
            // Call PxAgreement.DeleteAgreement
            $params = array(
                'accountNumber' => '',
                'agreementRef' => $agreement->getAgreementRef(),
            );

            $result = Mage::helper('payexautopay/api')->getPx()->DeleteAgreement($params);
            Mage::helper('payexautopay/tools')->debugApi($result, 'PxAgreement.DeleteAgreement');
        }

        // Remove Agreement
        $agreement->delete();

        // Redirect to back
        if (!empty($_SERVER['HTTP_REFERER'])) {
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit();
        } else {
            $this->_redirect('/', array('_secure' => true));
        }
    }
}
