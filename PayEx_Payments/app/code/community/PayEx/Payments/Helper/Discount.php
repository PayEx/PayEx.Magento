<?php

/**
 * Class AAIT_Shared_Helper_Discount
 *
 * Helps to figure out correct tax for discounts on quotes and orders
 *
 * Note: this is a complete copy from our (eValent.com) internal module
 * Ecom_Utils. Original class name is Ecom_Utils_Helper_Discount
 *
 * @author: Andre Klang <andre.klang@evalent.com>
 *
 */
class PayEx_Payments_Helper_Discount extends Mage_Core_Helper_Abstract
{

    /**
     * Return total discount incl OR excl tax, depending on settings
     * @param Mage_Sales_Model_Quote $quote
     * @return int
     */
    public function getDiscount($quote)
    {
        if (Mage::getStoreConfig('tax/cart_display/subtotal') > 1) {
            return $this->getDiscountData($quote)->getDiscountInclTax();
        }

        return $this->getDiscountData($quote)->getDiscountExclTax();
    }

    /**
     * Gets the total discount from $quote
     * inkl. and excl. tax
     * Data is returned as a Varien_Object with these data-keys set:
     *  - discount_incl_tax
     *  - discount_excl_tax
     *
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return Varien_Object
     */
    public function getDiscountData(Mage_Sales_Model_Quote $quote)
    {
        // Classic Mode
        if ($this->getDiscountCalcMode($quote) === 'classic') {
            $discount = -1 * ($quote->getDiscountAmount() + $quote->getShippingDiscountAmount());
            $return = new Varien_Object();
            return $return->setDiscountInclTax($discount)->setDiscountExclTax($discount);
        }

        // Advanced Mode
        /** @var Mage_Sales_Model_Quote_Address $shippingAddress */
        $shippingAddress = $quote->getShippingAddress();

        $discountIncl = 0;
        $discountExcl = 0;

        // find discount on the items
        foreach ($quote->getItemsCollection() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
            if (!Mage::helper('tax')->priceIncludesTax($quote->getStore())) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // find out tax-rate for the shipping
        if ($shippingAddress->getShippingInclTax() > 0 && $shippingAddress->getShippingAmount() > 0) {
            $shippingTaxRate = $shippingAddress->getShippingInclTax() / $shippingAddress->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // how much differs between $discountExcl and total discount?
        // (the difference is due to discount on the shipping)
        if (!Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore())) {
            $shippingDiscount = abs($quote->getShippingAddress()->getDiscountAmount()) - $discountExcl;
        } else {
            $shippingDiscount = abs($quote->getShippingAddress()->getDiscountAmount()) - $discountIncl;
        }

        // apply/remove tax to shipping-discount
        if (!Mage::helper('tax')->priceIncludesTax($quote->getStore())) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new Varien_Object();
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }

    /**
     * Return total discount incl OR excl tax, depending on view settings
     * This is used to display total discount for customer
     * @param Mage_Sales_Model_Order $order
     * @return int
     */
    public function getOrderDiscount(Mage_Sales_Model_Order $order)
    {
        if (Mage::getStoreConfig('tax/cart_display/subtotal', $order->getStore()) > 1) {
            return $this->getOrderDiscountData($order)->getDiscountInclTax();
        }

        return $this->getOrderDiscountData($order)->getDiscountExclTax();
    }

    /**
     * Gets the total discount from $order
     * inkl. and excl. tax
     * Data is returned as a Varien_Object with these data-keys set:
     *  - discount_incl_tax
     *  - discount_excl_tax
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return Varien_Object
     */
    public function getOrderDiscountData(Mage_Sales_Model_Order $order)
    {
        // Classic Mode
        if ($this->getDiscountCalcMode($order) === 'classic') {
            $discount = -1 * ($order->getDiscountAmount() + $order->getShippingDiscountAmount());
            $return = new Varien_Object();
            return $return->setDiscountInclTax($discount)->setDiscountExclTax($discount);
        }

        // Advanced Mode
        $discountIncl = 0;
        $discountExcl = 0;

        // find discount on the items
        foreach ($order->getItemsCollection() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
            if (!Mage::helper('tax')->priceIncludesTax($order->getStore())) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // find out tax-rate for the shipping
        if ($order->getShippingInclTax() > 0 && $order->getShippingAmount() > 0) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // get discount amount for shipping
        $shippingDiscount = (float)$order->getShippingDiscountAmount();

        // apply/remove tax to shipping-discount
        if (!Mage::helper('tax')->shippingPriceIncludesTax($order->getStore())) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        // Workaround: Apply Customer Tax: Before Discount + Apply Discount On Prices: Including Tax
        if (!Mage::helper('tax')->applyTaxAfterDiscount($order->getStore()) &&
            Mage::helper('tax')->discountTax($order->getStore())
        ) {
            // Use Discount + Tax to get correct discount for order total
            if ($discountExcl > 0) {
                $discountVatPercent = round((($discountIncl / $discountExcl) - 1) * 100);
                $discountIncl = $discountExcl + $order->getTaxAmount();
                $discountExcl = $discountIncl / (($discountVatPercent / 100) + 1);
            } else {
                $discountIncl = 0;
                $discountExcl = 0;
            }
        }

        $return = new Varien_Object();
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }

    /**
     * Get Discount Calculation Mode
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $obj
     * @return string
     */
    public function getDiscountCalcMode($obj)
    {
        $method = $obj->getPayment()->getMethodInstance();
        $discount_calc = $method->getConfigData('discount_calc', $obj->getStoreId());
        if (empty($discount_calc)) {
            $discount_calc = 'advanced';
        }
        
        return $discount_calc;
    }
}