<?php

class PayEx_Payments_Block_Adminhtml_Sales_Order_Create_Totals_Fee extends Mage_Core_Block_Abstract
{
    protected $_template = 'payex/sales/order/create/totals/fee.phtml';

    /**
     * Get Payment fee
     * @return float
     */
    public function getPaymentFee()
    {
        // @todo
        return 0;
    }
}
