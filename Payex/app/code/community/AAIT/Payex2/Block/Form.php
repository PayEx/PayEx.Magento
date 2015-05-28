<?php

class AAIT_Payex2_Block_Form extends Mage_Payment_Block_Form
{
  protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex2/form.phtml');
    }
}