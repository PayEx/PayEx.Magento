<?php

class PayEx_Payments_Model_Quote_Tax extends Mage_Sales_Model_Quote_Address_Total_Tax
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function getCode()
    {
        return 'payex_payment_fee_tax';
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if (!in_array($paymentMethod, self::$_allowed_methods) && (!count($address->getQuote()->getPaymentsCollection()) || !$address->getQuote()->getPayment()->hasMethodInstance())) {
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();
        if (!in_array($paymentMethod->getCode(), self::$_allowed_methods)) {
            return $this;
        }

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        if (!$address->getPayexPaymentFee()) {
            return $this;
        }

        $quote = $address->getQuote();

        // Calculate Payment Fee
        $price = (float)$paymentMethod->getConfigData('paymentfee', $quote->getStore()->getId());
        $tax_class = $paymentMethod->getConfigData('paymentfee_tax_class', $quote->getStore()->getId());
        if (!$price) {
            return $this;
        }

        $fee = Mage::helper('payex/fee')->getPaymentFeePrice($price, $tax_class);
        if (!$fee) {
            return $this;
        }

        $address->setBasePayexPaymentFeeTax($fee->getPaymentFeeTax());
        $address->setPayexPaymentFeeTax($quote->getStore()->convertPrice($fee->getPaymentFeeTax(), false));

        $quote->setBasePayexPaymentFeeTax($fee->getPaymentFeeTax());
        $quote->setPayexPaymentFeeTax($quote->getStore()->convertPrice($fee->getPaymentFeeTax(), false));

        // update taxes
        $address->setTaxAmount($address->getTaxAmount() + $fee->getPaymentFeeTax());
        $address->setBaseTaxAmount($address->getBaseTaxAmount() + $quote->getStore()->convertPrice($fee->getPaymentFeeTax(), false));

        // update totals
        $address->setBaseGrandTotal($address->getBaseGrandTotal() + $fee->getPaymentFeeTax());
        $address->setGrandTotal($address->getGrandTotal() + $quote->getStore()->convertPrice($fee->getPaymentFeeTax(), false));

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $store = $address->getQuote()->getStore();

        if (Mage::getSingleton('tax/config')->displayCartSubtotalBoth($store) || Mage::getSingleton('tax/config')->displayCartSubtotalInclTax($store)) {
            if ($address->getSubtotalInclTax() > 0) {
                $subtotalInclTax = $address->getSubtotalInclTax();
            } else {
                $subtotalInclTax = $address->getSubtotal()+$address->getTaxAmount()-$address->getShippingTaxAmount()-$address->getPaymentFeeTax();
            }

            $address->addTotal(array(
                'code'      => 'subtotal',
                'title'     => Mage::helper('sales')->__('Subtotal'),
                'value'     => $subtotalInclTax,
                'value_incl_tax' => $subtotalInclTax,
                'value_excl_tax' => $address->getSubtotal(),
            ));
        }
        return $this;
    }
}
