<?php
/**
 * PayEx Bank Debit Payment
 * Created by AAIT Team.
 */
class AAIT_Bankdebit_Model_Source_ClientLanguage
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'en-US',
                'label' => Mage::helper('bankdebit')->__('English')
            ),
            array(
                'value' => 'sv-SE',
                'label' => Mage::helper('bankdebit')->__('Swedish')
            ),
            array(
                'value' => 'nb-NO',
                'label' => Mage::helper('bankdebit')->__('Norway')
            ),
            array(
                'value' => 'da-DK',
                'label' => Mage::helper('bankdebit')->__('Danish')
            ),
            array(
                'value' => 'es-ES',
                'label' => Mage::helper('bankdebit')->__('Spanish')
            ),
            array(
                'value' => 'de-DE',
                'label' => Mage::helper('bankdebit')->__('German')
            ),
            array(
                'value' => 'fi-FI',
                'label' => Mage::helper('bankdebit')->__('Finnish')
            ),
            array(
                'value' => 'fr-FR',
                'label' => Mage::helper('bankdebit')->__('French')
            ),
            array(
                'value' => 'pl-PL',
                'label' => Mage::helper('bankdebit')->__('Polish')
            ),
            array(
                'value' => 'cs-CZ',
                'label' => Mage::helper('bankdebit')->__('Czech')
            ),
            array(
                'value' => 'hu-HU',
                'label' => Mage::helper('bankdebit')->__('Hungarian')
            ),
        );
    }
}