<?php

class PayEx_MasterPass_Block_Form extends Mage_Payment_Block_Form
{
  protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex_mp/form.phtml');
    }
}