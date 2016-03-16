<?php

class PayEx_Payments_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('payex')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('payex')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('payex')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('payex')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('payex')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('payex')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('payex')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('payex')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('payex')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('payex')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('payex')->__('Hungarian')
            ),
        );
    }
}