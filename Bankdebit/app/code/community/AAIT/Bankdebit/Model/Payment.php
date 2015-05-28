<?php

/**
 * PayEx Bank Debit Payment Model
 * Created by AAIT Team.
 */
class AAIT_Bankdebit_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Payment method code
     */
    public $_code = 'bankdebit';

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canFetchTransactionInfo = true;
    //protected $_canCancelInvoice        = true;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'bankdebit/info';
    protected $_formBlockType = 'bankdebit/form';

    /**
     * Init Class
     */
    public function __construct()
    {
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');

        Mage::helper('bankdebit/api')->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
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

        // Check currency
        $allowedCurrency = array('DKK', 'EUR', 'GBP', 'NOK', 'SEK', 'USD');
        return in_array($quote->getQuoteCurrencyCode(), $allowedCurrency);
    }

    /**
     * Validate
     * @return bool
     */
    public function validate()
    {
        Mage::helper('bankdebit/tools')->addToDebug('Action: Validate');
        return parent::validate();
    }

    /**
     * Get the redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        Mage::helper('bankdebit/tools')->addToDebug('Action: getOrderPlaceRedirectUrl');

        // Save Bank Id
        $bank_id = Mage::app()->getRequest()->getParam('payexbank');
        $this->getCheckout()->setBankId($bank_id);

        return Mage::getUrl('bankdebit/bankdebit/redirect', array('_secure' => true));
    }

    /**
     * Capture payment
     * @note In BankDebit used auto-capture
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Mage::helper('bankdebit/tools')->addToDebug('Action: Capture');

        parent::capture($payment, $amount);

        $transactionId = $payment->getLastTransId();
        //$transactionId = $transactionId . '-capture';

        // Add Capture Transaction
        $payment->setStatus(self::STATUS_APPROVED)
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(0);

        // Do nothing

        return $this;
    }

    /**
     * Cancel payment
     * @param   Varien_Object $payment
     * @return  $this
     */
    public function cancel(Varien_Object $payment)
    {
        Mage::helper('bankdebit/tools')->addToDebug('Action: Cancel');

        $transactionId = $payment->getLastTransId();

        // Add Cancel Transaction
        $payment->setStatus(self::STATUS_DECLINED)
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(1); // Closed

        // Do nothing

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
        Mage::helper('bankdebit/tools')->addToDebug('Action: Refund');

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
            Mage::throwException(Mage::helper('bankdebit')->__('Can\'t load last transaction.'));
        }

        // Get Transaction Details
        $details = $this->fetchTransactionInfo($payment, $transactionId);

        // Check for Capture and Authorize transaction only
        if ((int)$details['transactionStatus'] !== 6 && (int)$details['transactionStatus'] !== 0) {
            Mage::throwException(Mage::helper('bankdebit')->__('This payment has not yet captured.'));
        }

        $transactionNumber = $details['transactionNumber'];
        $order_id = $details['orderId'];
        if (!$order_id) {
            $order_id = $payment->getOrder()->getId();
        }

        // Call PxOrder.Credit5
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionNumber,
            'amount' => round($amount * 100),
            'orderId' => $order_id,
            'vatAmount' => 0,
            'additionalValues' => ''
        );
        $result = Mage::helper('bankdebit/api')->getPx()->Credit5($params);
        Mage::helper('bankdebit/tools')->debugApi($result, 'PxOrder.Credit');

        // Check Results
        if ($result['code'] == 'OK' && $result['errorCode'] == 'OK' && $result['description'] == 'OK') {
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
        Mage::helper('bankdebit/tools')->throwPayExException($result, 'PxOrder.Credit5');
    }

    /**
     * Void payment
     * @param Varien_Object $payment
     * @return $this
     */
    public function void(Varien_Object $payment)
    {
        Mage::helper('bankdebit/tools')->addToDebug('Action: Void');
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
        Mage::helper('bankdebit/tools')->addToDebug('Action: fetchTransactionInfo. ID ' . $transactionId);

        // Get Transaction Details
        $params = array(
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        );
        $details = Mage::helper('bankdebit/api')->getPx()->GetTransactionDetails2($params);
        Mage::helper('bankdebit/tools')->debugApi($details, 'PxOrder.GetTransactionDetails2');

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
        Mage::helper('bankdebit/tools')->throwPayExException($details, 'GetTransactionDetails2');
    }

    /**
     * Create Payment Block
     * @param $name
     * @return mixed
     */
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('bankdebit/form', $name)
            ->setMethod('bankdebit')
            ->setPayment($this->getPayment())
            ->setTemplate('bankdebit/form.phtml');
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
        return Mage::getSingleton('bankdebit/session');
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
