<?php

class AAIT_Payexautopay_Block_Widget extends Mage_Core_Block_Abstract implements Mage_Widget_Block_Interface
{

    protected function _toHtml() {
        if ( Mage::helper('payexautopay/agreement')->getCustomerAgreement() === AAIT_Payexautopay_Model_Payment::AGREEMENT_NOTEXISTS ) {
            return '';
        }
        $cancel_agreement_url   = Mage::getUrl('payexautopay/payexautopay/cancel_agreement', array('_secure' => true));
        $cancel_agreement_url   = "javascript:if(window.confirm('".Mage::helper('payexautopay')->__('Cancel agreement?')."')) { self.location.href = '".$cancel_agreement_url."' };";
        $cancel_agreement_label = Mage::helper('payexautopay')->__('Cancel agreement');
        $link = '<a href="'.$cancel_agreement_url.'">'.$cancel_agreement_label.'</a>';
        return $link;
    }
}