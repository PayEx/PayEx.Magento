<?php

class PayEx_Payments_Model_Fee_Config extends Mage_Tax_Model_Config
{
    /**
     * Shopping cart display settings
     */
    const XML_PATH_DISPLAY_CART_PAYEX_FEE = 'tax/cart_display/payex_fee';

    /**
     * Shopping cart display settings
     */
    const XML_PATH_DISPLAY_SALES_PAYEX_FEE = 'tax/sales_display/payex_fee';

    /**
     * Check if display cart prices fee included tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartPayExFeeInclTax($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_CART_PAYEX_FEE, $store) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartPayExFeeExclTax($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_CART_PAYEX_FEE, $store) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displayCartPayExFeeBoth($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_CART_PAYEX_FEE, $store) == self::DISPLAY_TYPE_BOTH;
    }

    /**
     * Check if display sales prices fee included tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesPayExFeeInclTax($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_SALES_PAYEX_FEE, $store) == self::DISPLAY_TYPE_INCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesPayExFeeExclTax($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_SALES_PAYEX_FEE, $store) == self::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * Check if display sales prices fee included and excluded tax
     * @param mixed $store
     * @return bool
     */
    public function displaySalesPayExFeeBoth($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DISPLAY_SALES_PAYEX_FEE, $store) == self::DISPLAY_TYPE_BOTH;
    }
}

