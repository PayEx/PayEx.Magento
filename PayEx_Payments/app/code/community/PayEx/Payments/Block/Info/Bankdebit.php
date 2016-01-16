<?php

class PayEx_Payments_Block_Info_Bankdebit extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/bankdebit/info.phtml');
    }
}
