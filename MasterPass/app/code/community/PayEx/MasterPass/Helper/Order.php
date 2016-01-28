<?php

class PayEx_MasterPass_Helper_Order extends AAIT_Shared_Helper_Order
{
    /**
     * @return PayEx_MasterPass_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("payex_mp");
    }

    /**
     * @return PayEx_MasterPass_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("payex_mp/tools");
    }

    /**
     * @return PayEx_MasterPass_Helper_Api
     */
    public function getApi(){
        return Mage::helper("payex_mp/api");
    }

    /**
     * Get Shopping Cart XML
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getShoppingCartXML($quote)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $ShoppingCart = $dom->createElement('ShoppingCart');
        $dom->appendChild($ShoppingCart);

        $ShoppingCart->appendChild($dom->createElement('CurrencyCode', $quote->getQuoteCurrencyCode()));
        $ShoppingCart->appendChild($dom->createElement('Subtotal', (int)(100 * $quote->getGrandTotal())));

        // Add Order Lines
        $items = $quote->getAllVisibleItems();
        /** @var $item Mage_Sales_Model_Quote_Item */
        foreach ($items as $item) {
            $product = $item->getProduct();

            $ShoppingCartItem = $dom->createElement('ShoppingCartItem');
            $ShoppingCartItem->appendChild($dom->createElement('Description', $item->getName()));
            $ShoppingCartItem->appendChild($dom->createElement('Quantity', (int) $item->getQty()));
            $ShoppingCartItem->appendChild($dom->createElement('Value', (int)bcmul($product->getFinalPrice(), 100)));
            $ShoppingCartItem->appendChild($dom->createElement('ImageURL', $product->getThumbnailUrl())); // NOTE: getThumbnailUrl is DEPRECATED!
            $ShoppingCart->appendChild($ShoppingCartItem);
        }

        return str_replace("\n", '', $dom->saveXML());
    }
}