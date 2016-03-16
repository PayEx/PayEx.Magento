<?php

class PayEx_Payments_Helper_Agreement extends Mage_Core_Helper_Abstract
{
    /**
     * Get Customer Agreement ID from Database
     * @return bool
     */
    public function getCustomerAgreement()
    {
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';
        $agreement = Mage::getModel('payex/agreement')->load($customer_id, 'customer_id');
        return $agreement->getId() ? $agreement->getAgreementRef() : false;
    }

    /**
     * Check status Customer Agreement
     * @param  $agreement_ref
     * @return bool | int
     */
    public function getPxAgreementStatus($agreement_ref)
    {
        if (!$agreement_ref) {
            return PayEx_Payments_Model_Payment_Autopay::AGREEMENT_NOTEXISTS;
        }

        // Init Environment
        Mage::getSingleton('payex/payment_autopay');

        // Call PxAgreement.AgreementCheck
        $params = array(
            'accountNumber' => '',
            'agreementRef' => $agreement_ref,
        );
        $result = Mage::helper('payex/api')->getPx()->AgreementCheck($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.AgreementCheck');

        // Check Errors
        if ($result['code'] !== 'OK' && $result['description'] !== 'OK') {
            return PayEx_Payments_Model_Payment_Autopay::AGREEMENT_NOTEXISTS;
        }

        $agreement_status = (int)$result['agreementStatus'];
        Mage::helper('payex/tools')->addToDebug('PxAgreement.AgreementCheck Status is ' . $agreement_status . ' (NotVerified = 0, Verified = 1, Deleted = 2)');
        return $agreement_status;
    }

}