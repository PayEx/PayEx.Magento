<?php

class AAIT_Factoring_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('factoring/form.phtml');
    }
}