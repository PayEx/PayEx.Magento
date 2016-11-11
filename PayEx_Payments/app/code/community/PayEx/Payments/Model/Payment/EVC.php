<?php

class PayEx_Payments_Model_Payment_EVC extends PayEx_Payments_Model_Payment_CC
{
    /**
     * Payment Method Code
     */
    const METHOD_CODE = 'payex_evc';

    /**
     * Payment method code
     */
    public $_code = self::METHOD_CODE;

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = true;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'payex/info_EVC';
    protected $_formBlockType = 'payex/form_EVC';


    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        $result = parent::assignData($data);
        return $result;
    }

    /**
     * Get the redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('payex/evc/redirect', array('_secure' => true));
    }
}
