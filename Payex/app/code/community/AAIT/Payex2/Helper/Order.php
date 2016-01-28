<?php
class AAIT_Payex2_Helper_Order extends AAIT_Shared_Helper_Order
{
    /**
     * @return AAIT_Payex2_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("payex2");
    }

    /**
     * @return AAIT_Payex2_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("payex2/tools");
    }

    /**
     * @return AAIT_Payex2_Helper_Api
     */
    public function getApi(){
        return Mage::helper("payex2/api");
    }

}