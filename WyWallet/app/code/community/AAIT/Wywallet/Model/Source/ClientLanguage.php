<?php

class AAIT_Wywallet_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('wywallet')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('wywallet')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('wywallet')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('wywallet')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('wywallet')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('wywallet')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('wywallet')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('wywallet')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('wywallet')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('wywallet')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('wywallet')->__('Hungarian')
            ),
        );
    }
}