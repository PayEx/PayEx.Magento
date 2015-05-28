<?php
/**
 * PayEx API Helper: API Handler
 * Created by AAIT Team.
 */
require_once(Mage::getBaseDir('lib') . '/Px/Px.php');

class AAIT_Bankdebit_Helper_Api extends Mage_Core_Helper_Abstract
{
    protected static $_px = null;

    /**
     * Get PayEx Api Handler
     * @static
     * @return Px
     */
    public static function getPx()
    {
        // Use Singleton
        if (is_null(self::$_px)) {
            self::$_px = new Px();
        }
        return self::$_px;
    }
}