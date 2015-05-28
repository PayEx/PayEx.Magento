<?php
/**
 * PayEx API Helper: Tools
 * Created by AAIT Team.
 */
class AAIT_Payexapi_Helper_Tools extends Mage_Core_Helper_Abstract
{
    /**
     * Throws PayEx Exception
     * @param $message
     * @param string $pxfunction
     * @return void
     */
    public function throwPayExException($message, $pxfunction = '')
    {
        $error_message = $this->debugApi($message, $pxfunction);
        Mage::throwException($error_message);
    }

    /**
     * Add PayEx Api Result to Debug Log
     * @param $message
     * @param string $pxfunction
     * @return string
     */
    public function debugApi($message, $pxfunction = '')
    {
        $error_message = '';
        if (is_array($message)) {
            if (isset($message['code']) && isset($message['description'])) {
                $error_message = Mage::helper('payexapi')->__($message['code']) . ' (' . Mage::helper('payexapi')->__($message['description']) . ')';
            } else {
                $error_message = Mage::helper('payexapi')->__('Unknown error');
            }

            if (!empty($message['thirdPartyError'])) {
                $error_message .= ' Third Party Error: ' . Mage::helper('payexapi')->__($message['thirdPartyError']);
            }
            if (!empty($message['transactionErrorCode']) && !empty($message['transactionErrorDescription'])) {
                $error_message .= ' Transaction Error: ' . Mage::helper('payexapi')->__($message['transactionErrorCode']) . ' (' . $message['transactionErrorDescription'] . ')';
            }
        } else {
            $error_message = Mage::helper('payexapi')->__($message);
        }

        $error_message = 'PayEx: ' . $pxfunction . ' ' . $error_message;
        $this->addToDebug($error_message);
        return $error_message;
    }

    /**
     * Add to Debug Log
     * @param string $message
     * @param string $order_id
     */
    public function addToDebug($message = '', $order_id = '')
    {
        if (!empty($order_id)) {
            $message .= ' OrderId: ' . $order_id;
        }
        Mage::log($message, null, 'payment_payexapi.log');
    }
}