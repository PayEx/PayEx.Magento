<?php
class AAIT_Payexautopay_Model_Mysql4_Agreement extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        // Note that "agreement_id" refers to the key field in your database table.
        $this->_init('payexautopay/payexautopay_agreement', 'agreement_id');
    }
}