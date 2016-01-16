<?php

class PayEx_Payments_Block_Form_Autopay extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/autopay/form.phtml');
    }
}