<?php
/**
 * PayEx Helper: Order
 * Created by AAIT Team.
 */

class AAIT_Payexautopay_Helper_Order extends AAIT_Shared_Helper_Order
{

    /**
     * @return AAIT_Payexautopay_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("payexautopay");
    }

    /**
     * @return AAIT_Payexautopay_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("payexautopay/tools");
    }

    /**
     * @return AAIT_Payexautopay_Helper_Api
     */
    public function getApi(){
        return Mage::helper("payexautopay/api");
    }
}