<?php
class AAIT_Bankdebit_Helper_Order extends AAIT_Shared_Helper_Order
{
    /**
     * @return AAIT_Bankdebit_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("bankdebit");
    }

    /**
     * @return AAIT_Bankdebit_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("bankdebit/tools");
    }

    /**
     * @return AAIT_Bankdebit_Helper_Api
     */
    public function getApi(){
        return Mage::helper("bankdebit/api");
    }

}