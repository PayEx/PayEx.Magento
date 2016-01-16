<?php

class PayEx_Payments_Model_Source_Banks
{
    protected $_options;

    public function toOptionArray($isMultiselect=false)
    {
        return array(
            // Sweden (SEK)
            array(
                'value' => 'NB',
                'label' => Mage::helper('payex')->__('Nordea Bank')
            ),
            array(
                'value' => 'FSPA',
                'label' => Mage::helper('payex')->__('Swedbank')
            ),
            array(
                'value' => 'SEB',
                'label' => Mage::helper('payex')->__('Svenska Enskilda Bank')
            ),
            array(
                'value' => 'SHB',
                'label' => Mage::helper('payex')->__('Handelsbanken')
            ),
            // Denmark (DKK)
            array(
                'value' => 'NB:DK',
                'label' => Mage::helper('payex')->__('Nordea Bank DK')
            ),
            array(
                'value' => 'DDB',
                'label' => Mage::helper('payex')->__('Den Danske Bank')
            ),
            // Norway (NOK)
            array(
                'value' => 'BAX',
                'label' => Mage::helper('payex')->__('BankAxess')
            ),
            // Finland (EUR)
            array(
                'value' => 'SAMPO',
                'label' => Mage::helper('payex')->__('Sampo')
            ),
            array(
                'value' => 'AKTIA',
                'label' => Mage::helper('payex')->__('Aktia, Säästöpankki')
            ),
            array(
                'value' => 'OP',
                'label' => Mage::helper('payex')->__('Osuuspanki, Pohjola, Oko')
            ),
            array(
                'value' => 'NB:FI',
                'label' => Mage::helper('payex')->__('Nordea Bank Finland')
            ),
            array(
                'value' => 'SHB:FI',
                'label' => Mage::helper('payex')->__('SHB:FI')
            ),
            array(
                'value' => 'SPANKKI',
                'label' => Mage::helper('payex')->__('SPANKKI')
            ),
            array(
                'value' => 'TAPIOLA',
                'label' => Mage::helper('payex')->__('TAPIOLA')
            ),
            array(
                'value' => 'AALAND',
                'label' => Mage::helper('payex')->__('Ålandsbanken')
            )
        );
    }
}