<?php

class PayEx_Payments_Model_Creditmemo_Total extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
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

        $basePayexPaymentFeeRefunded = $order->getBasePayexPaymentFeeRefunded();
        $payexPaymentFeeRefunded = $order->getPayexPaymentFeeRefunded();

        $basePayexPaymentFeeInvoiced = $order->getBasePayexPaymentFeeInvoiced();
        $payexPaymentFeeInvoiced = $order->getPayexPaymentFeeInvoiced();

        $basePayexPaymentFeeToRefund = abs($basePayexPaymentFeeInvoiced - $basePayexPaymentFeeRefunded);
        $payexPaymentFeeToRefund = abs($payexPaymentFeeInvoiced - $payexPaymentFeeRefunded);

        if ($payexPaymentFeeToRefund <= 0) {
            return $this;
        }

        $creditmemo->setBaseGrandTotal($creditmemoBaseGrandTotal + $payexPaymentFeeToRefund)
            ->setGrandTotal($creditmemoGrandTotal + $payexPaymentFeeToRefund)
            ->setBasePayexPaymentFee($basePayexPaymentFeeToRefund)
            ->setPayexPaymentFee($payexPaymentFeeToRefund);

        $order->setBasePayexPaymentFeeRefunded($basePayexPaymentFeeRefunded + $basePayexPaymentFeeToRefund)
            ->setPayexPaymentFeeRefunded($payexPaymentFeeRefunded + $payexPaymentFeeToRefund);

        return $this;
    }
}
