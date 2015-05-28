<?php

/**
 * PayEx 2 Payment
 * Created by AAIT Team.
 */
class AAIT_Payex2_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('payex2')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('payex2')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('payex2')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('payex2')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('payex2')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('payex2')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('payex2')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('payex2')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('payex2')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('payex2')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('payex2')->__('Hungarian')
            ),
        );
    }
}