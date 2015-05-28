<?php

/**
 * PayEx2 Payment
 * Created by AAIT Team.
 */
class AAIT_Payex2_Model_Source_PaymentView
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'PX',
                'label' => Mage::helper('payex2')->__('Payment Menu')
            ),
            array(
                'value' => 'CREDITCARD',
                'label' => Mage::helper('payex2')->__('Credit Card')
            ),
            array(
                'value' => 'INVOICE',
                'label' => Mage::helper('payex2')->__('Invoice')
            ),
            array(
                'value' => 'DIRECTDEBIT',
                'label' => Mage::helper('payex2')->__('Direct Debit')
            ),
            array(
                'value' => 'PAYPAL',
                'label' => Mage::helper('payex2')->__('PayPal')
            )
        );
    }
}