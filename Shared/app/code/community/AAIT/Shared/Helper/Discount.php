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
class AAIT_Shared_Helper_Discount extends AAIT_Shared_Helper_Data {
	
	/**
	 * Return total discount incl OR excl tax, depending on settings
	 * @param int
	 */
	public function getDiscount($quote){
		if(Mage::getStoreConfig('tax/cart_display/subtotal')>1) return $this->getDiscountData($quote)->getDiscountInclTax();
		else return $this->getDiscountData($quote)->getDiscountExclTax();
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
	public function getDiscountData(Mage_Sales_Model_Quote $quote) {

        // if catalog-prices includes tax
		$CatPriceIncl = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX);

        /** @var Mage_Sales_Model_Quote_Address $shippingAddress */
		$shippingAddress = $quote->getShippingAddress();

		$discountIncl = 0;
		$discountExcl = 0;

		// find discount on the items
		foreach ($quote->getItemsCollection() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
			if (!$CatPriceIncl) {
				$discountExcl += $item->getDiscountAmount();
				$discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
			} else {
				$discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
				$discountIncl += $item->getDiscountAmount();
			}
		}

		// find out tax-rate for the shipping
		if((float)$shippingAddress->getShippingInclTax() && (float)$shippingAddress->getShippingAmount())
			$shippingTaxRate = $shippingAddress->getShippingInclTax() / $shippingAddress->getShippingAmount();
		else
			$shippingTaxRate = 1;

		// how much differs between $discountExcl and total discount?
        // (the difference is due to discount on the shipping)
		if(!$CatPriceIncl) $shippingDiscount = abs($quote->getShippingAddress()->getDiscountAmount()) - $discountExcl;
		else $shippingDiscount = abs($quote->getShippingAddress()->getDiscountAmount()) - $discountIncl;

		// apply/remove tax to shipping-discount
		if(!$CatPriceIncl){
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
	 * @param int
	 */
	public function getOrderDiscount(Mage_Sales_Model_Order $order){
		if(Mage::getStoreConfig('tax/cart_display/subtotal',$order->getStore())>1) return $this->getOrderDiscountData($order)->getDiscountInclTax();
		else return $this->getOrderDiscountData($order)->getDiscountExclTax();
	
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
	public function getOrderDiscountData(Mage_Sales_Model_Order $order) {

        // if catalog-prices includes tax
		$CatPriceIncl = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX,$order->getStore());
		
		$discountIncl = 0;
		$discountExcl = 0;

        // find discount on the items
		foreach ($order->getItemsCollection() as $item) {
            /** @var Mage_Sales_Model_Quote_Item $item */
			if (!$CatPriceIncl) {
				$discountExcl += $item->getDiscountAmount();
				$discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
			} else {
				$discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
				$discountIncl += $item->getDiscountAmount();
			}
		}

        // find out tax-rate for the shipping
		if((float) $order->getShippingInclTax() && (float) $order->getShippingAmount())
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        else
            $shippingTaxRate = 1;
		
		// get discount amount for shipping
		$shippingDiscount = $order->getShippingDiscountAmount();

        // apply/remove tax to shipping-discount
		if(!$CatPriceIncl){
			$discountIncl += $shippingDiscount * $shippingTaxRate;
			$discountExcl += $shippingDiscount;
		} else {
			$discountIncl += $shippingDiscount;
			$discountExcl += $shippingDiscount / $shippingTaxRate;
		}
		

		$return = new Varien_Object();
		return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
	}
}