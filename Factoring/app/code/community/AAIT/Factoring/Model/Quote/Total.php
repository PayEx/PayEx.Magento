<?php

class AAIT_Factoring_Model_Quote_Total extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    public function getCode()
    {
        return 'factoring_payment_fee';
    }

    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if ($paymentMethod !== 'factoring' && (!count($address->getQuote()->getPaymentsCollection()) || !$address->getQuote()->getPayment()->hasMethodInstance())) {
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();
        if ($paymentMethod->getCode() !== 'factoring') {
            return $this;
        }

        $items = $address->getAllItems();
        if (!count($items)) {
            return $this;
        }

        $address->setBaseFactoringPaymentFee(0);
        $address->setFactoringPaymentFee(0);

        // Get totals
        $address1 = clone $address;
        foreach ($address1->getTotalCollector()->getCollectors() as $model) {
            if ($model->getCode() !== $this->getCode()) {
                $model->collect($address1);
            }
        }

        // Address is the reference for grand total
        // Calculated total is $address1->getGrandTotal()
        $fee = (float)Mage::getSingleton('factoring/fee')->getPaymentFee($address1);
        if (!$fee) {
            return $this;
        }

        $baseTotal = $address->getBaseGrandTotal();
        $baseTotal += $fee;

        $address->setBaseFactoringPaymentFee($fee);
        $address->setFactoringPaymentFee($address->getQuote()->getStore()->convertPrice($fee, false));

        // update totals
        $address->setBaseGrandTotal($baseTotal);
        $address->setGrandTotal($address->getQuote()->getStore()->convertPrice($baseTotal, false));

        return $this;
    }

    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');
        $paymentMethod = Mage::app()->getStore()->isAdmin() && isset($paymentMethod['method']) ? $paymentMethod['method'] : null;
        if ($paymentMethod !== 'factoring' && (!count($address->getQuote()->getPaymentsCollection()) || !$address->getQuote()->getPayment()->hasMethodInstance())) {
            return $this;
        }

        $paymentMethod = $address->getQuote()->getPayment()->getMethodInstance();
        if ($paymentMethod->getCode() !== 'factoring') {
            return $this;
        }

        $fee = $address->getFactoringPaymentFee();
        if ($fee > 0) {
            $address->addTotal(array(
                'code' => $this->getCode(),
                'title' => Mage::helper('factoring')->__('Payment fee'),
                'value' => $fee,
            ));
        }
        return $this;
    }
}
