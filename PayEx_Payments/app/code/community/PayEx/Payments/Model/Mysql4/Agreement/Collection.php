<?php

class PayEx_Payments_Model_Mysql4_Agreement_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('payex/agreement');
    }
}
