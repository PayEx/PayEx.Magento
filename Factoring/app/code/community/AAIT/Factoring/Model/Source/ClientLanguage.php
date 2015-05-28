<?php

class AAIT_Factoring_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('factoring')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('factoring')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('factoring')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('factoring')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('factoring')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('factoring')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('factoring')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('factoring')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('factoring')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('factoring')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('factoring')->__('Hungarian')
            ),
        );
    }
}