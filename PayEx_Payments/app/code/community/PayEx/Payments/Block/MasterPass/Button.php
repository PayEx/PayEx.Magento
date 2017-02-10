<?php

class PayEx_Payments_Block_MasterPass_Button extends Mage_Core_Block_Template
{
    /**
     * Get MasterPass Checkout URL
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::getUrl('payex/masterpass/checkout', array('_secure' => true));
    }

    /**
     * Get MasterPass Logo
     * @return string
     */
    public function getImageUrl()
    {
        return $this->getSkinUrl('images/payex/masterpass.png', array('_secure' => true));
    }
}
