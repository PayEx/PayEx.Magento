<?php

class PayEx_Payments_Model_Mysql4_Agreement extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {
        // Note that "agreement_id" refers to the key field in your database table.
        $this->_init('payex/payex_agreement', 'agreement_id');
    }
}
