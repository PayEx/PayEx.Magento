<?php

/**
 * PayEx Bank Debit Helper: Paygate data helper
 * Created by AAIT Team.
 */
class AAIT_Bankdebit_Helper_Data extends Mage_Core_Helper_Abstract {
    const XML_PATH_PAYEX_METHODS = 'bankdebit';

    public function getMethodInstance($code) {
        $key = self::XML_PATH_PAYEX_METHODS . '/' . $code . '/model';
        $class = Mage::getStoreConfig($key);
        if (!$class) {
            Mage::throwException(Mage::helper('bankdebit')->__('Can not configuration for payment method with code: %s', $code));
        }
        return Mage::getModel($class);
    }

}
