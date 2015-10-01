<?php
require_once(Mage::getBaseDir('lib') . '/Px/Px.php');

class AAIT_SocialSecurityNumber_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected static $_px = null;

    /**
     * Get Country Code by SSN
     * @param $ssn
     *
     * @return string|bool
     */
    public function getCountryCodeBySSN($ssn) {
        $rules = array(
            'NO' => '/^[0-9]{6,6}((-[0-9]{5,5})|([0-9]{2,2}((-[0-9]{5,5})|([0-9]{1,1})|([0-9]{3,3})|([0-9]{5,5))))$/',
            'SE' => '/^[0-9]{6,6}(([0-9]{2,2}[-\+]{1,1}[0-9]{4,4})|([-\+]{1,1}[0-9]{4,4})|([0-9]{4,6}))$/',
            //'FI' => '/^[0-9]{6,6}(([A\+-]{1,1}[0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{1,1}-{0,1}[0-9A-FHJK-NPR-Y]{1,1}))$/i',
            //'DK' => '/^[0-9]{8,8}([0-9]{2,2})?$/',
            //'NL' => '/^[0-9]{7,9}$/'
        );

        foreach ($rules as $country_code => $pattern) {
            if ((bool)preg_match($pattern, $ssn)) {
                return $country_code;
            }
        }

        return false;
    }

    /**
     * Get Name Parser Instance
     * @return FullNameParser
     */
    public function getNameParser()
    {
        if (!class_exists('FullNameParser')) {
            require_once dirname(__FILE__) . '/../library/parser.php';
        }

        return new FullNameParser();
    }

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
