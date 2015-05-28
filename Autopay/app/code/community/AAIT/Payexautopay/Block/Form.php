<?php

class AAIT_Payexautopay_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payexautopay/form.phtml');
    }
}