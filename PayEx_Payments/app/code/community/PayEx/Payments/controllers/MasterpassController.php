<?php

class PayEx_Payments_MasterpassController extends Mage_Core_Controller_Front_Action
{
    public function _construct()
    {
        // Bootstrap PayEx Environment
        Mage::getSingleton('payex/payment_MasterPass');
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

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payex/order')->getCalculatedOrderAmount($order)->getAmount();

        $additional = 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' . urlencode(Mage::helper('payex/order')->getShoppingCartXML($order));

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
            'returnUrl' => Mage::getUrl('payex/masterpass/success', array('_secure' => true)),
            'view' => 'CREDITCARD',
            'agreementRef' => '',
            'cancelUrl' => Mage::getUrl('payex/masterpass/cancel', array('_secure' => true)),
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

        //$order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Set Pending Payment status
        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage::helper('payex')->__('The customer was redirected to PayEx.'));
        $order->save();

        // Redirect to PayEx
        Mage::app()->getFrontController()->getResponse()->setRedirect($redirectUrl)->sendResponse();
    }

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

        $amount = Mage::helper('payex/order')->getCalculatedOrderAmount($order)->getAmount();

        // Call PxOrder.FinalizeTransaction
        $params = array(
            'accountNumber'   => '',
            'orderRef'        => $orderRef,
            'amount'          => round($amount * 100),
            'vatAmount'       => 0,
            'clientIPAddress' => Mage::helper('core/http')->getRemoteAddr()
        );
        $result = Mage::helper('payex/api')->getPx()->FinalizeTransaction($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxOrder.FinalizeTransaction');
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            // Check order has already been purchased
            //if ( $result['code'] === 'Order_AlreadyPerformed' ) {
            //    @todo
            //}

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

    /**
     * MasterPass Checkout Button Action
     */
    public function checkoutAction()
    {
        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        try {
            if (!$quote->hasItems()) {
                Mage::throwException(Mage::helper('payex')->__('You don\'t have any items in your cart'));
            }

            if (!$quote->getGrandTotal() && !$quote->hasNominalItems()) {
                Mage::throwException(Mage::helper('payex')->__('Order total is too small'));
            }

            // Set Payment Method
            $quote->setPaymentMethod('payex_masterpass');

            // Update totals
            $quote->collectTotals();

            // Create an Order ID for the customer's quote
            $quote->reserveOrderId()->save();
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            return $this->_redirect('checkout/cart');
        }

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = (Mage::getSingleton('payex/payment_MasterPass')->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get Additional Values
        $additional = 'USEMASTERPASS=1&RESPONSIVE=1&SHOPPINGCARTXML=' . urlencode(Mage::helper('payex/order')->getShoppingCartXML($quote));

        // Call PxOrder.Initialize8
        $params = array(
            'accountNumber' => '',
            'purchaseOperation' => $operation,
            'price' => round($quote->getGrandTotal() * 100),
            'priceArgList' => '',
            'currency' => $quote->getQuoteCurrencyCode(),
            'vat' => 0,
            'orderID' => $quote->getReservedOrderId(),
            'productNumber' => $quote->getReservedOrderId(),
            'description' => Mage::app()->getStore()->getName(),
            'clientIPAddress' => Mage::helper('core/http')->getRemoteAddr(),
            'clientIdentifier' => 'USERAGENT=' . Mage::helper('core/http')->getHttpUserAgent(),
            'additionalValues' => $additional,
            'externalID' => '',
            'returnUrl' => Mage::getUrl('payex/masterpass/mp_success', array('_secure' => true)),
            'view' => 'CREDITCARD',
            'agreementRef' => '',
            'cancelUrl' => Mage::getUrl('payex/masterpass/cancel', array('_secure' => true)),
            'clientLanguage' => Mage::getSingleton('payex/payment_MasterPass')->getConfigData('clientlanguage')
        );
        $result = Mage::helper('payex/api')->getPx()->Initialize8($params);
        Mage::helper('payex/tools')->addToDebug('PxOrder.Initialize8:' . $result['description']);

        // Check Errors
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
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

        //$order_ref = $result['orderRef'];
        $redirectUrl = $result['redirectUrl'];

        // Set quote to inactive
        Mage::getSingleton('checkout/session')->setPayexQuoteId(Mage::getSingleton('checkout/session')->getQuoteId());
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();

        // Redirect to PayEx
        Mage::app()->getFrontController()->getResponse()->setRedirect($redirectUrl)->sendResponse();
    }

    public function mp_successAction() 
    {
        // Check OrderRef
        $orderRef = $this->getRequest()->getParam('orderRef');
        if (empty($orderRef)) {
            $this->_redirect('checkout/cart');
            return;
        }

        // Set quote to active
        if ($quoteId = Mage::getSingleton('checkout/session')->getPayexQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
            }
        }

        /** @var $quote Mage_Sales_Model_Quote */
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        // Call PxOrder.GetApprovedDeliveryAddress
        $params = array(
            'accountNumber' => '',
            'orderRef'      => $orderRef
        );
        $result = Mage::helper('payex/api')->getPx()->GetApprovedDeliveryAddress($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);

            Mage::getSingleton('checkout/session')->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        // @todo "Please check shipping address information. Please enter the state/province."

        // Billing Address
        $billingAddress = array(
            'firstname' => $result['firstName'],
            'lastname' => $result['lastName'],
            'company' => '',
            'email' => $result['eMail'],
            'street' => array(
                $result['address1'],
                trim($result['address2'] . ' ' . $result['address3'])
            ),
            'city' => ucfirst($result['city']),
            'region_id' => '',
            'region' => '',
            'postcode' => str_replace(' ', '', $result['postalCode']),
            'country_id' => $result['country'],
            'telephone' => $result['phone'],
            'fax' => '',
            'customer_password' => '',
            'confirm_password' => '',
            'save_in_address_book' => '0',
            'use_for_shipping' => '1',
        );

        // Set Billing Address
        $quote->getBillingAddress()
            ->addData($billingAddress);

        // Set Shipping Address
        $shipping = $quote->getShippingAddress()
            ->addData($billingAddress);

        // Set Shipping Method
        if (!$quote->isVirtual()) {
            $shipping_method = Mage::getSingleton('payex/payment_MasterPass')->getConfigData('shipping_method');
            $shipping->setCollectShippingRates(true)->collectShippingRates()
                ->setShippingMethod($shipping_method);

            //$quote->getShippingAddress()->setShippingMethod($shipping_method);
        }

        // Use Check Money Payment Method
        $quote->setPaymentMethod('payex_masterpass');

        // Update totals
        $quote->collectTotals();

        // Set Checkout Method
        if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
            // Use Guest Checkout
            $quote->setCheckoutMethod('guest')
                ->setCustomerId(null)
                ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        } else {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $quote
                ->setCustomer($customer)
                ->setCheckoutMethod($customer->getMode())
                ->save();
        }

        $quote->getPayment()->importData(array('method' => 'payex_masterpass'));

        // Save Order
        try {
            $quote->save();

            /** @var Mage_Sales_Model_Service_Quote $service */
            $service = Mage::getModel('sales/service_quote', $quote);
            if (method_exists($service, 'submitAll')) {
                $service->submitAll();
                $order = $service->getOrder();
            } else {
                $order = $service->submit();
            }
        } catch (Exception $e) {
            Mage::getSingleton('checkout/session')->addError($e->getMessage());
            $this->_redirect('checkout/cart');
            return;
        }

        // Get Order Id
        $order_id = $order->getIncrementId();

        /** @var PayEx_Payments_Model_Payment_Abstract $method */
        $method = $order->getPayment()->getMethodInstance();

        // Call PxOrder.FinalizeTransaction
        $params = array(
            'accountNumber'   => '',
            'orderRef'        => $orderRef,
            'amount'          => round($order->getGrandTotal() * 100),
            'vatAmount'       => 0,
            'clientIPAddress' => Mage::helper('core/http')->getRemoteAddr()
        );
        $result = Mage::helper('payex/api')->getPx()->FinalizeTransaction($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);

            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment($message);
            $order->save();

            // Check order has already been purchased
            //if ($result['code'] === 'Order_AlreadyPerformed') {
            //    @todo
            //}

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

                // Empty Cart
                //$quote->setIsActive(0)->save();
                Mage::getSingleton('checkout/cart')->truncate()->save();

                // Redirect to Success page
                $session = Mage::getSingleton('checkout/type_onepage')->getCheckout();
                $session->setLastSuccessQuoteId($quote->getId());
                $session->setLastQuoteId($quote->getId());
                $session->setLastOrderId($order->getId());

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

                Mage::getSingleton('checkout/session')->addError($transaction->getMessage());
                $this->_redirect('checkout/cart');
        }
    }
}
