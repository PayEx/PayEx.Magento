<?php

class AAIT_PartPayment_Block_Adminhtml_Sales_Order_Create_Totals_Fee extends Mage_Core_Block_Abstract
{
    protected $_template = 'partpayment/sales/order/create/totals/fee.phtml';

    /**
     * Get Payment fee
     * @return float
     */
    public function getPaymentFee()
    {
        return Mage::getSingleton('partpayment/fee')->getPaymentFee();
    }
}
