<?php

class PayEx_Payments_Block_Info_Invoice extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/invoice/info.phtml');
    }
}
