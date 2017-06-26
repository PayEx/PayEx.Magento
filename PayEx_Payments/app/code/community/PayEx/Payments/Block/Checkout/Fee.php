<?php

class PayEx_Payments_Block_Checkout_Fee extends Mage_Checkout_Block_Total_Default
{
    protected $_template = 'payex/checkout/fee.phtml';

    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );
    
    /**
     * Get Payment Fee Incl Tax
     * @return mixed
     */
    public function getPayExFeeIncludeTax()
    {
        return $this->getTotal()->getAddress()->getPayexPaymentFee() +
            $this->getTotal()->getAddress()->getPayexPaymentFeeTax();
    }

    /**
     * Get Payment Fee Excl Tax
     * @return mixed
     */
    public function getPayExFeeExcludeTax()
    {
        return $this->getTotal()->getAddress()->getPayexPaymentFee();
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @return mixed
     */
    public function displayCartPayExFeeBoth()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displayCartPayExFeeBoth($this->getStore());
    }

    /**
     * Check if display cart prices fee included tax
     * @return mixed
     */
    public function displayCartPayExFeeInclTax()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displayCartPayExFeeInclTax($this->getStore());
    }

    /**
     * Get "Exclude Tax" Label
     * @return mixed
     */
    public function getExcludeTaxLabel()
    {
        return Mage::helper('tax')->getIncExcTaxLabel(false);
    }

    /**
     * Get "Include Tax" Label
     * @return mixed
     */
    public function getIncludeTaxLabel()
    {
        return Mage::helper('tax')->getIncExcTaxLabel(true);
    }

}
