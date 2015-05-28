<?php

/**
 * PayEx AutoPay Helper: Agreement Tools
 * Created by AAIT Team.
 */
class AAIT_Payexautopay_Helper_Agreement extends Mage_Core_Helper_Abstract
{
    /**
     * Get Customer Agreement ID from Database
     * @return bool
     */
    public function getCustomerAgreement()
    {
        $customer_id = (Mage::getSingleton('customer/session')->isLoggedIn() == true) ? Mage::getSingleton('customer/session')->getCustomer()->getId() : '0';
        $agreement = Mage::getModel('payexautopay/agreement')->load($customer_id, 'customer_id');
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
            return AAIT_Payexautopay_Model_Payment::AGREEMENT_NOTEXISTS;
        }

        // Init Payment
        Mage::getSingleton('payexautopay/payment');

        // Call PxAgreement.AgreementCheck
        $params = array(
            'accountNumber' => '',
            'agreementRef' => $agreement_ref,
        );
        $result = Mage::helper('payexautopay/api')->getPx()->AgreementCheck($params);
        Mage::helper('payexautopay/tools')->debugApi($result, 'PxAgreement.AgreementCheck');

        // Check Errors
        if ($result['code'] !== 'OK' && $result['description'] !== 'OK') {
            return AAIT_Payexautopay_Model_Payment::AGREEMENT_NOTEXISTS;
        }

        $agreement_status = (int)$result['agreementStatus'];
        Mage::helper('payexautopay/tools')->addToDebug('PxAgreement.AgreementCheck Status is ' . $agreement_status . ' (NotVerified = 0, Verified = 1, Deleted = 2)');
        return $agreement_status;
    }

}