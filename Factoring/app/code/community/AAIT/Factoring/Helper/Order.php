<?php

/**
 * PayEx Helper: Order
 * Created by AAIT Team.
 */
class AAIT_Factoring_Helper_Order extends Mage_Core_Helper_Abstract
{
    /**
     * @return AAIT_Factoring_Helper_Data
     */
    public function getHelper(){
        return Mage::helper("factoring");
    }

    /**
     * @return AAIT_Factoring_Helper_Tools
     */
    public function getTools(){
        return Mage::helper("factoring/tools");
    }

    /**
     * @return AAIT_Factoring_Helper_Api
     */
    public function getApi(){
        return Mage::helper("factoring/api");
    }
}