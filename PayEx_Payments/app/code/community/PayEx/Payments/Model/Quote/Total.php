<?php

class PayEx_Payments_Model_Quote_Total extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function getCode()
    {
        return 'payex_payment_fee';
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        if (Mage::app()->getStore()->isAdmin()) {
            $paymentMethod = isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        }

        if (!in_array($paymentMethod, self::$_allowed_methods) &&
            (
                count($address->getQuote()->getPaymentsCollection()) === 0 ||
                !$address->getQuote()->getPayment()->hasMethodInstance()
            )
        ) {
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();
        if (!in_array($paymentMethod->getCode(), self::$_allowed_methods)) {
            return $this;
        }

        $items = $address->getAllItems();
        if (count($items) === 0) {
            return $this;
        }

        $quote = $address->getQuote();

        $address->setBasePayexPaymentFee(0);
        $address->setBasePayexPaymentFeeTax(0);
        $address->setPayexPaymentFee(0);
        $address->setPayexPaymentFeeTax(0);

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

        $address->setBasePayexPaymentFee(
            $fee->getPaymentFeeExclTax()
        );
        $address->setPayexPaymentFee(
            $address->getQuote()->getStore()->convertPrice($fee->getPaymentFeeExclTax(), false)
        );

        $quote->setBasePayexPaymentFee(
            $fee->getPaymentFeeExclTax()
        );
        $quote->setPayexPaymentFee(
            $address->getQuote()->getStore()->convertPrice($fee->getPaymentFeeExclTax(), false)
        );

        // Update totals
        $address->setBaseGrandTotal(
            $address->getBaseGrandTotal() + $fee->getPaymentFeeExclTax()
        );
        $address->setGrandTotal(
            $address->getGrandTotal() +
            $address->getQuote()->getStore()->convertPrice($fee->getPaymentFeeExclTax(), false)
        );

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $fee = $address->getPayexPaymentFee();
        if ($fee > 0) {
            $address->addTotal(
                array(
                'code' => $this->getCode(),
                'title' => Mage::helper('payex')->__('Payment fee'),
                'value' => $fee,
                )
            );
        }

        return $this;
    }
}
