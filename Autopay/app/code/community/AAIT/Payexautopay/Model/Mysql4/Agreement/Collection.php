<?php
class AAIT_Payexautopay_Model_Mysql4_Agreement_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('payexautopay/agreement');
    }
}
