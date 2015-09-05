<?php

class AAIT_PartPayment_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('partpayment')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('partpayment')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('partpayment')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('partpayment')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('partpayment')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('partpayment')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('partpayment')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('partpayment')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('partpayment')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('partpayment')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('partpayment')->__('Hungarian')
            ),
        );
    }
}