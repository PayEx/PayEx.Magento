<?php

class PayEx_Payments_Model_Payment_Financing extends PayEx_Payments_Model_Payment_Abstract
{
    /**
     * Payment method code
     */
    public $_code = 'payex_financing';

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'payex/info_financing';
    protected $_formBlockType = 'payex/form_financing';

    /**
     * Supported currencies
     * See http://pim.payex.com/Section3/currencycodes.htm
     */
    static protected $_allowCurrencyCode = array('DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD');

    /**
     * Get initialized flag status
     * @return true
     */
    public function isInitializeNeeded()
    {
        return true;
    }

    /**
     * Instantiate state and set it to state object
     * @param  $paymentAction
     * @param  $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        // Set Initial Order Status
        $state = Mage_Sales_Model_Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);
    }

    /**
     * Get config action to process initialization
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    /**
     * Check whether payment method can be used
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if (parent::isAvailable($quote) === false) {
            return false;
        }

        if (!$quote) {
            return false;
        }

        return true;
    }

    /**
     * Validate
     * @return bool
     */
    public function validate()
    {
        Mage::helper('payex/tools')->addToDebug('Action: Validate');

        // Get the iso2 Country Code from the billing section
        $country_code = $this->getQuote()->getBillingAddress()->getCountry();

        $postcode = $this->getQuote()->getBillingAddress()->getPostcode();

        // Get current currency
        $paymentInfo = $this->getInfoInstance();

        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $currency_code = $paymentInfo->getOrder()->getBaseCurrencyCode();
        } else {
            //$currency_code = $this->getQuote()->getBaseCurrencyCode();
            $currency_code = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }

        // Check supported currency
        if (!in_array($currency_code, self::$_allowCurrencyCode)) {
            Mage::throwException(
                Mage::helper('payex')->__('Selected currency code (%s) is not compatible with PayEx', $currency_code)
            );
        }

        // Validate Product names
        if (!$this->getConfigData('replace_illegal')) {
            if ($paymentInfo->getQuote()) {
                $items = $paymentInfo->getQuote()->getAllVisibleItems();
                /** @var $item Mage_Sales_Model_Quote_Item */
                foreach ($items as $item) {
                    $re = "/[a-zA-Z0-9_:!#=?\\[\\]@{}´ %-À-ÖØ-öø-ú]*/u";
                    $product_name = $item->getName();

                    $matches = array();
                    preg_match($re, $product_name, $matches);
                    $test = implode('', $matches);
                    if (md5($product_name) !== md5($test)) {
                        Mage::throwException(
                            Mage::helper('payex')->__('Product name "%s" contains invalid characters.', $product_name)
                        );
                    }
                }
            }
        }

        if (empty($country_code)) {
            Mage::throwException(Mage::helper('payex')->__('Please select country.'));
        }

        if (empty($postcode)) {
            Mage::throwException(Mage::helper('payex')->__('Please enter postcode.'));
        }

        // Get Social Security Number
        // You can use 8111032382 in Test Environment
        $ssn = trim(Mage::app()->getRequest()->getParam('social-security-number'));
        if (empty($ssn)) {
            Mage::throwException(Mage::helper('payex')->__('Please enter Social Security Number.'));
        }

        // Save Social Security Number
        $this->getCheckout()->setSocialSecurityNumber($ssn);
    }

    /**
     * Get the redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        Mage::helper('payex/tools')->addToDebug('Action: getOrderPlaceRedirectUrl');

        return Mage::getUrl('payex/financing/redirect', array('_secure' => true));
    }

    /**
     * Capture payment
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Mage::helper('payex/tools')->addToDebug('Action: Capture');

        parent::capture($payment, $amount);

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for capture.'));
        }

        if (!$payment->getLastTransId()) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }

        $payment->setAmount($amount);

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($transactionId);
        if (!$transaction) {
            Mage::throwException(Mage::helper('payex')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
        if (!is_array($details) || count($details) === 0) {
            $details = $this->fetchTransactionInfo($payment, $transactionId);
            $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $details);
            $transaction->save();
        }

        // Not to execute for Sale transactions
        if ((int)$details['transactionStatus'] !== 3) {
            Mage::throwException(Mage::helper('payex')->__('Can\'t capture captured order.'));
            //return $this;
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $payment->getOrder()->getIncrementId();
        $available = $details['amount'] / 100;

        // Prevent Rounding Issue
        $value = abs(sprintf("%.2f", $amount) - sprintf("%.2f", $available));
        if ($value > 0 && $value < 0.2) {
            $amount = $available;
            $payment->setAmount($amount);
        }

        $xml = Mage::helper('payex/order')->getInvoiceExtraPrintBlocksXML($payment->getOrder());

        // Call PxOrder.Capture5
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round(100 * $amount),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => 'FINANCINGINVOICE_ORDERLINES=' . urlencode($xml)
        );
        $result = Mage::helper('payex/api')->getPx()->Capture5($params);
        Mage::helper('payex/tools')->addToDebug('PXOrder.Capture5:' . $result['description'], $order_id);

        // Check Results
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Note: Order Status will be changed in Observer

            // Add Capture Transaction
            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(0);

            // Add Transaction fields
            $payment->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        Mage::helper('payex/tools')->throwPayExException($result, 'PxOrder.Capture5');
        return $this;
    }

    /**
     * Cancel payment
     * @param   Varien_Object $payment
     * @return  $this
     */
    public function cancel(Varien_Object $payment)
    {
        Mage::helper('payex/tools')->addToDebug('Action: Cancel');

        parent::cancel($payment);

        if (!$payment->getLastTransId()) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($transactionId);
        if (!$transaction) {
            Mage::throwException(Mage::helper('payex')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $this->fetchTransactionInfo($payment, $transactionId);
        $details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        // Not to execute for Sale transactions
        if ((int)$details['transactionStatus'] !== 3) {
            Mage::throwException(Mage::helper('payex')->__('Unable to execute cancel.'));
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $details['orderId'];
        if (!$order_id) {
            $order_id = $payment->getOrder()->getId();
        }

        // Call PXOrder.Cancel2
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber
        );
        $result = Mage::helper('payex/api')->getPx()->Cancel2($params);
        Mage::helper('payex/tools')->addToDebug('PxOrder.Cancel2:' . $result['description'], $order_id);

        // Check Results
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Add Cancel Transaction
            $payment->setStatus(self::STATUS_DECLINED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(1); // Closed

            // Add Transaction fields
            $payment->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        Mage::helper('payex/tools')->throwPayExException($result, 'PxOrder.Cancel2');
        return $this;
    }

    /**
     * Refund capture
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        Mage::helper('payex/tools')->addToDebug('Action: Refund');

        parent::refund($payment, $amount);

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($transactionId);
        if (!$transaction) {
            Mage::throwException(Mage::helper('payex')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $details = $this->fetchTransactionInfo($payment, $transactionId);
        //$details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        // Check for Capture and Authorize transaction only
        if ((int)$details['transactionStatus'] !== 6 && (int)$details['transactionStatus'] !== 0) {
            Mage::throwException(Mage::helper('payex')->__('This payment has not yet captured.'));
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $details['orderId'];
        if (!$order_id) {
            $order_id = $payment->getOrder()->getId();
        }

        // Call PXOrder.PXOrder.Credit5
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round(100 * $amount),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => ''
        );
        $result = Mage::helper('payex/api')->getPx()->Credit5($params);
        Mage::helper('payex/tools')->addToDebug('PxOrder.Credit5:' . $result['description'], $order_id);

        // Check Results
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Add Credit Transaction
            $payment->setAnetTransType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
            $payment->setAmount($amount);

            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(0); // No-Closed

            // Add Transaction fields
            $payment->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        Mage::helper('payex/tools')->throwPayExException($result, 'PxOrder.Credit5');
        return $this;
    }

    /**
     * Void payment
     * @param Varien_Object $payment
     * @return Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        Mage::helper('payex/tools')->addToDebug('Action: Void');
        return $this->cancel($payment);
    }

    /**
     * Fetch transaction details info
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        Mage::helper('payex/tools')->addToDebug('Action: fetchTransactionInfo. ID ' . $transactionId);

        parent::fetchTransactionInfo($payment, $transactionId);

        // Get Transaction Details
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        );
        $details = Mage::helper('payex/api')->getPx()->GetTransactionDetails2($params);
        Mage::helper('payex/tools')->addToDebug('PxOrder.GetTransactionDetails2:' . $details['description']);

        // Check Results
        if ($details['code'] === 'OK' && $details['errorCode'] === 'OK' && $details['description'] === 'OK') {
            // Filter details
            foreach ($details as $key => $value) {
                if (empty($value)) {
                    unset($details[$key]);
                }
            }

            return $details;
        }

        // Show Error
        Mage::helper('payex/tools')->throwPayExException($details, 'GetTransactionDetails2');
    }

    public function getStandardCheckoutFormFields()
    {
        return array();
    }

    /**
     * Check void availability
     * @param   Varien_Object $payment
     * @return  bool
     */
    public function canVoid(Varien_Object $payment)
    {
        if ($payment instanceof Mage_Sales_Model_Order_Invoice
            || $payment instanceof Mage_Sales_Model_Order_Creditmemo
        ) {
            return false;
        }

        return $this->_canVoid;
    }

    public function canEdit()
    {
        return false;
    }

    public function canUseInternal()
    {
        return $this->_canUseInternal;
    }

    public function canUseForMultishipping()
    {
        return $this->_canUseForMultishipping;
    }

    public function onOrderValidate(Mage_Sales_Model_Order_Payment $payment)
    {
        return $this;
    }

    public function onInvoiceCreate(Mage_Sales_Model_Order_Invoice $payment)
    {
    }

    public function canCapture()
    {
        return $this->_canCapture;
    }

    public function canFetchTransactionInfo()
    {
        return $this->_canFetchTransactionInfo;
    }
}
