<?php

class AAIT_Factoring_Model_Source_Mode
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'FINANCING',
                'label' => Mage::helper('factoring')->__('Financing Invoice')
            ),
            //array(
            //    'value' => 'FACTORING',
            //    'label' => Mage::helper('factoring')->__('Invoice 2.0 (Factoring)')
            //),
            array(
                'value' => 'CREDITACCOUNT',
                'label' => Mage::helper('factoring')->__('Part Payment')
            ),
            array(
                'value' => 'SELECT',
                'label' => Mage::helper('factoring')->__('User select')
            ),
        );
    }
}