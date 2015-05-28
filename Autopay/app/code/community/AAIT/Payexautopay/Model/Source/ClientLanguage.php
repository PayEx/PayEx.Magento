<?php
/**
 * PayEx Autopay Payment
 * Created by AAIT Team.
 */
class AAIT_Payexautopay_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('payexautopay')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('payexautopay')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('payexautopay')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('payexautopay')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('payexautopay')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('payexautopay')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('payexautopay')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('payexautopay')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('payexautopay')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('payexautopay')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('payexautopay')->__('Hungarian')
            ),
        );
    }
}