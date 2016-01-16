<?php

class PayEx_Payments_Model_Source_PaymentView
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'PX',
                'label' => Mage::helper('payex')->__('Payment Menu')
            ),
            array(
                'value' => 'CREDITCARD',
                'label' => Mage::helper('payex')->__('Credit Card')
            ),
            array(
                'value' => 'INVOICE',
                'label' => Mage::helper('payex')->__('Invoice (Ledger Service)')
            ),
            array(
                'value' => 'DIRECTDEBIT',
                'label' => Mage::helper('payex')->__('Direct Debit')
            ),
            array(
                'value' => 'PAYPAL',
                'label' => Mage::helper('payex')->__('PayPal')
            )
        );
    }
}