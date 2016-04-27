<?php

abstract class PayEx_Payments_Model_Payment_Abstract extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Init Class
     */
    public function __construct()
    {
        $accountnumber = $this->getConfigData('accountnumber');
        $encryptionkey = $this->getConfigData('encryptionkey');
        $debug = (bool)$this->getConfigData('debug');

        Mage::helper('payex/api')->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
    }

    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Fetch transaction info
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        // Init PayEx Environment
        $accountnumber = $this->getConfigData('accountnumber', $payment->getOrder()->getStoreId());
        $encryptionkey = $this->getConfigData('encryptionkey', $payment->getOrder()->getStoreId());
        $debug = (bool)$this->getConfigData('debug', $payment->getOrder()->getStoreId());

        Mage::helper('payex/api')->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);
    }
}