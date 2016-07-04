<?php

class PayEx_Payments_Model_Creditmemo_Tax extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    protected static $_allowed_methods = array(
        'payex_financing',
        'payex_partpayment',
        'payex_invoice'
    );

    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        if (!in_array($paymentMethod, self::$_allowed_methods)) {
            return $this;
        }

        $creditmemoBaseGrandTotal = $creditmemo->getBaseGrandTotal();
        $creditmemoGrandTotal = $creditmemo->getGrandTotal();
        $creditmemoBaseTaxAmount = $creditmemo->getBaseTaxAmount();
        $creditmemoTaxAmount = $creditmemo->getTaxAmount();

        $basePayexPaymentFeeTaxRefunded = $order->getBasePayexPaymentFeeTaxRefunded();
        $payexPaymentFeeTaxRefunded = $order->getPayexPaymentFeeTaxRefunded();

        $basePayexPaymentFeeTaxInvoiced = $order->getBasePayexPaymentFeeTaxInvoiced();
        $payexPaymentFeeTaxInvoiced = $order->getPayexPaymentFeeTaxInvoiced();

        $basePayexPaymentFeeTaxToRefund = abs($basePayexPaymentFeeTaxInvoiced - $basePayexPaymentFeeTaxRefunded);
        $payexPaymentFeeTaxToRefund = abs($payexPaymentFeeTaxInvoiced - $payexPaymentFeeTaxRefunded);

        if ($basePayexPaymentFeeTaxToRefund <= 0) {
            return $this;
        }

        $creditmemo->setBaseGrandTotal($creditmemoBaseGrandTotal + $basePayexPaymentFeeTaxToRefund)
            ->setGrandTotal($creditmemoGrandTotal + $payexPaymentFeeTaxToRefund)
            ->setBaseTaxAmount($creditmemoBaseTaxAmount + $basePayexPaymentFeeTaxToRefund)
            ->setTaxAmount($creditmemoTaxAmount + $payexPaymentFeeTaxToRefund)
            ->setBasePayexPaymentFeeTax($basePayexPaymentFeeTaxToRefund)
            ->setPayexPaymentFeeTax($payexPaymentFeeTaxToRefund);

        $order->setBasePayexPaymentFeeTaxRefunded($basePayexPaymentFeeTaxRefunded + $basePayexPaymentFeeTaxToRefund)
            ->setPayexPaymentFeeTaxRefunded($payexPaymentFeeTaxRefunded + $payexPaymentFeeTaxToRefund);

        return $this;
    }
}