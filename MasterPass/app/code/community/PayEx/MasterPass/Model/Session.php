<?php

class PayEx_MasterPass_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('payex_mp');
    }
}
