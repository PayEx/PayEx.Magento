<?php

class PayEx_Payments_Helper_Fee extends Mage_Core_Helper_Abstract
{
    /**
     * Get Payment Fee Price
     * @param float $fee
     * @param int $tax_class
     * @return Varien_Object
     */
    public function getPaymentFeePrice($fee, $tax_class)
    {
        // Get Tax Rate
        $request = Mage::getSingleton('tax/calculation')->getRateRequest(
            $this->getQuote()->getShippingAddress(),
            $this->getQuote()->getBillingAddress(),
            $this->getQuote()->getCustomerTaxClassId(),
            $this->getQuote()->getStore()
        );
        $taxRate = Mage::getSingleton('tax/calculation')
            ->getRate($request->setProductClassId($tax_class));

        $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($this->getQuote()->getStore());
        $taxAmount = Mage::getSingleton('tax/calculation')->calcTaxAmount($fee, $taxRate, $priceIncludeTax, true);

        if ($priceIncludeTax) {
            $fee -= $taxAmount;
        }

        $result = new Varien_Object();
        $result->setPaymentFeeExclTax($fee)
            ->setPaymentFeeInclTax($fee + $taxAmount)
            ->setPaymentFeeTax($taxAmount);
        return $result;
    }

    /**
     * Get Quote
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getModel('checkout/cart')->getQuote();
    }
}