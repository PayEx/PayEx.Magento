<?php

class PayEx_Payments_Model_Agreement extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('payex/agreement');
    }
}
