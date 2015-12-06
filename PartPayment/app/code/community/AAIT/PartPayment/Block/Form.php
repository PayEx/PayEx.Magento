<?php

class AAIT_PartPayment_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('partpayment/form.phtml');
    }

    /**
     * Get Saved SSN from SSN extension
     * @return mixed
     */
    public function getPayexSSN()
    {
        return Mage::getSingleton('checkout/session')->getPayexSSN();
    }
}