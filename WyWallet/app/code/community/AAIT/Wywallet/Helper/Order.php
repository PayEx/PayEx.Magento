<?php
class AAIT_Wywallet_Helper_Order extends AAIT_Shared_Helper_Order
{
    /**
     * @return AAIT_Wywallet_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("wywallet");
    }

    /**
     * @return AAIT_Wywallet_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("wywallet/tools");
    }

    /**
     * @return AAIT_Wywallet_Helper_Api
     */
    public function getApi(){
        return Mage::helper("wywallet/api");
    }
}