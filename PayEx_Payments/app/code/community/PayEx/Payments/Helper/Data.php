<?php

class PayEx_Payments_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Billing Agreement Config
     */
    const XML_PATH_PAYEX_BILLING_AGREEMENT = 'payment/payex_billing_agreement/active';

    /**
     * PayEx CC Payment View Config
     */
    const XML_PATH_PAYEX_CC_PAYMENT_VIEW = 'payment/payex_cc/paymentview';

    /**
     * Cache for shouldAskToCreateBillingAgreement()
     *
     * @var bool
     */
    protected static $_shouldAskToCreateBillingAgreement = null;

    /**
     * Check whether customer should be asked confirmation whether to sign a billing agreement
     *
     * @param int $customerId
     * @return bool
     */
    public function shouldAskToCreateBillingAgreement($customerId)
    {
        if (null === self::$_shouldAskToCreateBillingAgreement) {
            self::$_shouldAskToCreateBillingAgreement = false;
            if ($customerId && $this->isBillingAgreementEnabled()) {
                if (Mage::getModel('sales/billing_agreement')->needToCreateForCustomer($customerId)) {
                    self::$_shouldAskToCreateBillingAgreement = true;
                }
            }
        }

        return self::$_shouldAskToCreateBillingAgreement;
    }

    /**
     * Check if Billing Agreement Enabled
     * @param mixed $store
     * @return bool
     */
    public function isBillingAgreementEnabled($store = null)
    {
        return (bool)Mage::getStoreConfig(self::XML_PATH_PAYEX_BILLING_AGREEMENT, $store);
    }

    /**
     * Check if Billing Agreement Enabled
     * @param mixed $store
     * @return bool
     */
    public function isBillingAgreementAvailable($store = null)
    {
        return $this->isBillingAgreementEnabled($store) &&
            Mage::getStoreConfig(self::XML_PATH_PAYEX_CC_PAYMENT_VIEW, $store) === 'CREDITCARD';
    }
}
