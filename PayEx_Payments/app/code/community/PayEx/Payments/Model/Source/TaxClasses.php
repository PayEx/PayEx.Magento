<?php

class PayEx_Payments_Model_Source_TaxClasses
{
    public function toOptionArray()
    {
        $collection = Mage::getModel('tax/class')
            ->getCollection()
            ->setClassTypeFilter('PRODUCT')
            ->toOptionArray();
        return $collection;
    }
}
