<?php

class PayEx_MasterPass_Block_Button extends Mage_Core_Block_Template {
    /**
     * Get MasterPass Checkout URL
     * @return string
     */
    public function getCheckoutUrl()
    {
        return Mage::getUrl('payex_mp/checkout/masterpass', array('_secure' => true));
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
