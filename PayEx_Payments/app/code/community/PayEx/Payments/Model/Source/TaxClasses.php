<?php

class PayEx_Payments_Model_Source_TaxClasses
{
    public function toOptionArray()
    {
        $options = Mage::getModel('tax/class_source_product')->toOptionArray();
        return $options;
    }
}
