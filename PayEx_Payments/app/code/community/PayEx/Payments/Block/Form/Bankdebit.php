<?php

class PayEx_Payments_Block_Form_Bankdebit extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/bankdebit/form.phtml');
    }
}
