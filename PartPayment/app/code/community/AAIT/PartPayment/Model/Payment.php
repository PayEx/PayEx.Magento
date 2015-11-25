<?php

class AAIT_PartPayment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Payment method code
     */
    public $_code = 'partpayment';

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
    protected $_infoBlockType = 'partpayment/info';
    protected $_formBlockType = 'partpayment/form';

    /**
     * Supported currencies
     * See http://pim.payex.com/Section3/currencycodes.htm
     */
    static protected $_allowCurrencyCode = array('DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD');

    /**
     * Init Class
     */
    public function __construct()
    {
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');

        Mage::helper('partpayment/api')->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
    }

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

        return true;
    }

    /**
     * Validate
     * @return bool
     */
    public function validate()
    {
        Mage::helper('partpayment/tools')->addToDebug('Action: Validate');

        // Get the iso2 Country Code from the billing section
        $country_code = $this->getQuote()->getBillingAddress()->getCountry();

        // Get Postcode
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
            Mage::throwException(Mage::helper('partpayment')->__('Selected currency code (%s) is not compatible with PayEx', $currency_code));
        }

        // Get Social Security Number
        // You can use 8111032382 in Test Environment
        $ssn = Mage::app()->getRequest()->getParam('social-security-number');

        $params = array(
            'accountNumber' => '',
            'paymentMethod' => $country_code === 'SE' ? 'PXFINANCINGINVOICESE' : 'PXFINANCINGINVOICENO',
            'ssn' => $ssn,
            'zipcode' => $postcode,
            'countryCode' => $country_code,
            'ipAddress' => Mage::helper('core/http')->getRemoteAddr()
        );
        $result = Mage::helper('partpayment/api')->getPx()->GetAddressByPaymentMethod($params);
        Mage::helper('partpayment/tools')->addToDebug('PxOrder.GetAddressByPaymentMethod:' . $result['description']);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            // Show Error Message
            Mage::helper('partpayment/tools')->throwPayExException($result, 'PxOrder.GetAddressByPaymentMethod');
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
        Mage::helper('partpayment/tools')->addToDebug('Action: getOrderPlaceRedirectUrl');
        return Mage::getUrl('partpayment/payment/redirect', array('_secure' => true));
    }

    /**
     * Capture payment
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Mage::helper('partpayment/tools')->addToDebug('Action: Capture');

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
            Mage::throwException(Mage::helper('partpayment')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $this->fetchTransactionInfo($payment, $transactionId);
        $details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        // Not to execute for Sale transactions
        if ((int)$details['transactionStatus'] !== 3) {
            Mage::throwException(Mage::helper('partpayment')->__('Can\'t capture captured order.'));
            //return $this;
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $payment->getOrder()->getIncrementId();

        // Prevent Rounding Issue
        // Difference can be ~0.0099999999999909
        $order_amount = Mage::helper('partpayment/order')->getCalculatedOrderAmount($payment->getOrder())->amount;
        $value = abs(sprintf("%.2f", $order_amount) - sprintf("%.2f", $amount));
        if ($value > 0 && $value < 0.011) {
            $amount = $order_amount;
        }

        $xml = Mage::helper('partpayment/order')->getInvoiceExtraPrintBlocksXML($payment->getOrder());

        // Call PxOrder.Capture5
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round(100 * $amount),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => 'FINANCINGINVOICE_ORDERLINES=' . urlencode($xml)
        );
        $result = Mage::helper('partpayment/api')->getPx()->Capture5($params);
        Mage::helper('partpayment/tools')->addToDebug('PXOrder.Capture5:' . $result['description'], $order_id);

        // Check Results
        if ($result['code'] === 'OK' && $result['errorCode'] === 'OK' && $result['description'] === 'OK') {
            // Note: Order Status will be changed in Observer

            // Add Capture Transaction
            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($result['transactionNumber'])
                ->setIsTransactionClosed(0);

            // @todo Get Invoice Link URL using PxOrder.InvoiceLinkGet

            // Add Transaction fields
            $payment->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $result);
            return $this;
        }

        // Show Error
        Mage::helper('partpayment/tools')->throwPayExException($result, 'PxOrder.Capture5');
        return $this;
    }

    /**
     * Cancel payment
     * @param   Varien_Object $payment
     * @return  $this
     */
    public function cancel(Varien_Object $payment)
    {
        Mage::helper('partpayment/tools')->addToDebug('Action: Cancel');

        if (!$payment->getLastTransId()) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($transactionId);
        if (!$transaction) {
            Mage::throwException(Mage::helper('partpayment')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $this->fetchTransactionInfo($payment, $transactionId);
        $details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        // Not to execute for Sale transactions
        if ((int)$details['transactionStatus'] !== 3) {
            Mage::throwException(Mage::helper('partpayment')->__('Unable to execute cancel.'));
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
        $result = Mage::helper('partpayment/api')->getPx()->Cancel2($params);
        Mage::helper('partpayment/tools')->addToDebug('PxOrder.Cancel2:' . $result['description'], $order_id);

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
        Mage::helper('partpayment/tools')->throwPayExException($result, 'PxOrder.Cancel2');
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
        Mage::helper('partpayment/tools')->addToDebug('Action: Refund');

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
            Mage::throwException(Mage::helper('partpayment')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $details = $this->fetchTransactionInfo($payment, $transactionId);
        //$details = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

        // Check for Capture and Authorize transaction only
        if ((int)$details['transactionStatus'] !== 6 && (int)$details['transactionStatus'] !== 0) {
            Mage::throwException(Mage::helper('partpayment')->__('This payment has not yet captured.'));
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
        $result = Mage::helper('partpayment/api')->getPx()->Credit5($params);
        Mage::helper('partpayment/tools')->addToDebug('PxOrder.Credit5:' . $result['description'], $order_id);

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
        Mage::helper('partpayment/tools')->throwPayExException($result, 'PxOrder.Credit5');
        return $this;
    }

    /**
     * Void payment
     * @param Varien_Object $payment
     * @return Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        Mage::helper('partpayment/tools')->addToDebug('Action: Void');
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
        Mage::helper('partpayment/tools')->addToDebug('Action: fetchTransactionInfo. ID ' . $transactionId);

        // Get Transaction Details
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        );
        $details = Mage::helper('partpayment/api')->getPx()->GetTransactionDetails2($params);
        Mage::helper('partpayment/tools')->addToDebug('PxOrder.GetTransactionDetails2:' . $details['description']);

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
        Mage::helper('partpayment/tools')->throwPayExException($details, 'GetTransactionDetails2');
    }

    /**
     * Create Payment Block
     * @param $name
     * @return mixed
     */
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('partpayment/form', $name)
            ->setMethod('partpayment')
            ->setPayment($this->getPayment())
            ->setTemplate('partpayment/form.phtml');
        return $block;
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

    public function getSession()
    {
        return Mage::getSingleton('partpayment/session');
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    public function canFetchTransactionInfo()
    {
        return $this->_canFetchTransactionInfo;
    }

}