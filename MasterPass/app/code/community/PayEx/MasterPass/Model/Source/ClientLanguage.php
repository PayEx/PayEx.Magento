<?php

class PayEx_MasterPass_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('payex_mp')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('payex_mp')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('payex_mp')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('payex_mp')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('payex_mp')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('payex_mp')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('payex_mp')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('payex_mp')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('payex_mp')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('payex_mp')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('payex_mp')->__('Hungarian')
            ),
        );
    }
}