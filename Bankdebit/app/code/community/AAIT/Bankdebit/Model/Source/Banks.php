<?php
/**
 * PayEx Bank Debit Payment
 * Created by AAIT Team.
 */
class AAIT_Bankdebit_Model_Source_Banks
{
    protected $_options;

    public function toOptionArray($isMultiselect=false)
    {
        return array(
            // Sweden (SEK)
            array(
                'value' => 'NB',
                'label' => Mage::helper('bankdebit')->__('Nordea Bank')
            ),
            array(
                'value' => 'FSPA',
                'label' => Mage::helper('bankdebit')->__('Swedbank')
            ),
            array(
                'value' => 'SEB',
                'label' => Mage::helper('bankdebit')->__('Svenska Enskilda Bank')
            ),
            array(
                'value' => 'SHB',
                'label' => Mage::helper('bankdebit')->__('Handelsbanken')
            ),
            // Denmark (DKK)
            array(
                'value' => 'NB:DK',
                'label' => Mage::helper('bankdebit')->__('Nordea Bank DK')
            ),
            array(
                'value' => 'DDB',
                'label' => Mage::helper('bankdebit')->__('Den Danske Bank')
            ),
            // Norway (NOK)
            array(
                'value' => 'BAX',
                'label' => Mage::helper('bankdebit')->__('BankAxess')
            ),
            // Finland (EUR)
            array(
                'value' => 'SAMPO',
                'label' => Mage::helper('bankdebit')->__('Sampo')
            ),
            array(
                'value' => 'AKTIA',
                'label' => Mage::helper('bankdebit')->__('Aktia, Säästöpankki')
            ),
            array(
                'value' => 'OP',
                'label' => Mage::helper('bankdebit')->__('Osuuspanki, Pohjola, Oko')
            ),
            array(
                'value' => 'NB:FI',
                'label' => Mage::helper('bankdebit')->__('Nordea Bank Finland')
            ),
            array(
                'value' => 'SHB:FI',
                'label' => Mage::helper('bankdebit')->__('SHB:FI')
            ),
            array(
                'value' => 'SPANKKI',
                'label' => Mage::helper('bankdebit')->__('SPANKKI')
            ),
            array(
                'value' => 'TAPIOLA',
                'label' => Mage::helper('bankdebit')->__('TAPIOLA')
            ),
            array(
                'value' => 'AALAND',
                'label' => Mage::helper('bankdebit')->__('Ålandsbanken')
            )
        );
    }
}