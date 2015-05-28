<?php

/**
 * PayEx Autopay: Agreemnet Model
 * Created by AAIT Team.
 */
class AAIT_Payexautopay_Model_Agreement extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('payexautopay/agreement');
    }
}
