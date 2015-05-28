<?php
class AAIT_Payexapi_Model_Payexapi extends Mage_Payment_Model_Method_Abstract
{
    /**
     * Payment method code
     */
    public $_code = 'payexapi';

    /**
     * Availability options
     */
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = false;
    protected $_canUseForMultishipping = false;
    protected $_canFetchTransactionInfo = false;
}