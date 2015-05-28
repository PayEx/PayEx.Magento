<?php
/**
 * Paygate data helper
 */
class AAIT_Factoring_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYEX_METHODS = 'factoring';

    public function getMethodInstance($code)
    {
        $key = self::XML_PATH_PAYEX_METHODS . '/' . $code . '/model';
        $class = Mage::getStoreConfig($key);
        if (!$class) {
            Mage::throwException($this->__('Can not configuration for payment method with code: %s', $code));
        }
        return Mage::getModel($class);
    }
}
