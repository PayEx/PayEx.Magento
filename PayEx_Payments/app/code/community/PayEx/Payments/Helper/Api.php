<?php

require_once(Mage::getBaseDir('lib') . '/PayEx.Ecommerce.Php/src/PayEx/Px.php');

class PayEx_Payments_Helper_Api extends Mage_Core_Helper_Abstract
{
    protected static $_px = null;

    /**
     * Get PayEx Api Handler
     * @static
     * @return PayEx\Px
     */
    public static function getPx()
    {
        // Use Singleton
        if (is_null(self::$_px)) {
            self::$_px = new PayEx\Px();

            // Set User Agent
            $modules = Mage::getConfig()->getNode('modules')->children();
            $modulesArray = (array)$modules;
            self::$_px->setUserAgent(sprintf("PayEx.Ecommerce.Php/%s PHP/%s Magento%s/%s PayEx.Magento/%s",
                \PayEx\Px::VERSION,
                phpversion(),
                Mage::getEdition(),
                Mage::getVersion(),
                (string)$modulesArray['PayEx_Payments']->version
            ));
        }
        return self::$_px;
    }
}
