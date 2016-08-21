<?php

class PayEx_Payments_Block_Form_CC extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/cc/form.phtml');
    }

    /**
     * Set data to block
     *
     * @return Mage_Core_Block_Abstract
     */
    protected function _beforeToHtml()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if (
            $customerId > 0 &&
            Mage::helper('payex')->isBillingAgreementAvailable() &&
            $this->canCreateBillingAgreement()
        ) {
            $this->setCreateBACode(PayEx_Payments_Model_Payment_Agreement::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);
        }
        return parent::_beforeToHtml();
    }
}