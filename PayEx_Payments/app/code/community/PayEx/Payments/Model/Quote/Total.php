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

        $address->setBasePayexPaymentFee(0);
        $address->setBasePayexPaymentFeeTax(0);
        $address->setPayexPaymentFee(0);
        $address->setPayexPaymentFeeTax(0);

        // Get totals
        $address1 = clone $address;
        foreach ($address1->getTotalCollector()->getCollectors() as $model) {
            if ($model->getCode() !== $this->getCode()) {
                $model->collect($address1);
            }
        }

        // Address is the reference for grand total
        // Calculated total is $address1->getGrandTotal()
        $price = (float)$paymentMethod->getConfigData('paymentfee');
        $tax_class = $paymentMethod->getConfigData('paymentfee_tax_class');
        if (!$price) {
            return $this;
        }

        // Get Payment Fee
        $fee = Mage::helper('payex/fee')->getPaymentFeePrice($price, $tax_class);

        $baseTotal = $address->getBaseGrandTotal();
        $baseTotal += $fee->getPaymentFeePrice() + $fee->getPaymentFeeTax();

        $address->setBasePayexPaymentFee($fee->getPaymentFeePrice());
        $address->setBasePayexPaymentFeeTax($fee->getPaymentFeeTax());
        $address->setPayexPaymentFee($address->getQuote()->getStore()->convertPrice($fee->getPaymentFeePrice(), false));
        $address->setPayexPaymentFeeTax($address->getQuote()->getStore()->convertPrice($fee->getPaymentFeeTax(), false));

        // update totals
        $address->setBaseGrandTotal($baseTotal);
        $address->setGrandTotal($address->getQuote()->getStore()->convertPrice($baseTotal, false));

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
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

        $fee = $address->getPayexPaymentFee();
        if ($fee > 0) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('payex')->__('Payment fee'),
                'value' => $fee,
            ));
        }

        $fee_tax = $address->getPayexPaymentFeeTax();
        if ($fee_tax > 0) {
            $address->addTotal(array(
                'code' => $this->getCode() . '_tax',
                'title' => Mage::helper('payex')->__('Payment fee (tax)'),
                'value' => $fee_tax,
            ));
        }

        return $this;
    }
}
