<?php
/**
 * PayEx Bank Debit Helper: Tools
 * Created by AAIT Team.
 */
class AAIT_Bankdebit_Helper_Tools extends Mage_Core_Helper_Abstract
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
                $error_message = Mage::helper('bankdebit')->__($message['code']) . ' (' . Mage::helper('bankdebit')->__($message['description']) . ')';

            } else {
                $error_message = Mage::helper('bankdebit')->__('Unknown error');
            }

            if (!empty($message['thirdPartyError'])) {
                $error_message .= ' Third Party Error: ' . Mage::helper('bankdebit')->__($message['thirdPartyError']);
            }
            if (!empty($message['transactionErrorCode']) && !empty($message['transactionErrorDescription'])) {
                $error_message .= ' Transaction Error: ' . Mage::helper('bankdebit')->__($message['transactionErrorCode']) . ' (' . $message['transactionErrorDescription'] . ')';
            }
        } else {
            $error_message = Mage::helper('bankdebit')->__($message);
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
        Mage::log($message, null, 'payment_payexbankdebit.log');
    }

    /**
     * Get verbose error message by Error Code
     * @param $errorCode
     * @return string | false
     */
    public function getErrorMessageByCode($errorCode)
    {
        $errorMessages = array(
            'REJECTED_BY_ACQUIRER' => Mage::helper('bankdebit')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Error_Generic' => Mage::helper('bankdebit')->__('An unhandled exception occurred'),
            '3DSecureDirectoryServerError' => Mage::helper('bankdebit')->__('A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification'),
            'AcquirerComunicationError' => Mage::helper('bankdebit')->__('Communication error with the acquiring bank'),
            'AmountNotEqualOrderLinesTotal' => Mage::helper('bankdebit')->__('The sum of your order lines is not equal to the price set in initialize'),
            'CardNotEligible' => Mage::helper('bankdebit')->__('Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information'),
            'CreditCard_Error' => Mage::helper('bankdebit')->__('Some problem occurred with the credit card, your customer can contact their bank for more information'),
            'PaymentRefusedByFinancialInstitution' => Mage::helper('bankdebit')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Merchant_InvalidAccountNumber' => Mage::helper('bankdebit')->__('The merchant account number sent in on request is invalid'),
            'Merchant_InvalidIpAddress' => Mage::helper('bankdebit')->__('The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile'),
            'Access_MissingAccessProperties' => Mage::helper('bankdebit')->__('The merchant does not have access to requested functionality'),
            'Access_DuplicateRequest' => Mage::helper('bankdebit')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Admin_AccountTerminated' => Mage::helper('bankdebit')->__('The merchant account is not active'),
            'Admin_AccountDisabled' => Mage::helper('bankdebit')->__('The merchant account is not active'),
            'ValidationError_AccountLockedOut' => Mage::helper('bankdebit')->__('The merchant account is locked out'),
            'ValidationError_Generic' => Mage::helper('bankdebit')->__('Generic validation error'),
            'ValidationError_HashNotValid' => Mage::helper('bankdebit')->__('The hash on request is not valid, this might be due to the encryption key being incorrect'),
            'ValidationError_InvalidParameter' => Mage::helper('bankdebit')->__('One of the input parameters has invalid data. See paramName and description for more information'),
            'OperationCancelledbyCustomer' => Mage::helper('bankdebit')->__('The operation was cancelled by the client'),
            'PaymentDeclinedDoToUnspecifiedErr' => Mage::helper('bankdebit')->__('Unexpecter error at 3rd party'),
            'InvalidAmount' => Mage::helper('bankdebit')->__('The amount is not valid for this operation'),
            'NoRecordFound' => Mage::helper('bankdebit')->__('No data found'),
            'OperationNotAllowed' => Mage::helper('bankdebit')->__('The operation is not allowed, transaction is in invalid state'),
            'ACQUIRER_HOST_OFFLINE' => Mage::helper('bankdebit')->__('Could not get in touch with the card issuer'),
            'ARCOT_MERCHANT_PLUGIN_ERROR' => Mage::helper('bankdebit')->__('The card could not be verified'),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED' => Mage::helper('bankdebit')->__('There is a problem with this card'),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED' => Mage::helper('bankdebit')->__('The card expired'),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => Mage::helper('bankdebit')->__('Insufficient funds'),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT' => Mage::helper('bankdebit')->__('Incorrect amount'),
            'USER_CANCELED' => Mage::helper('bankdebit')->__('Payment cancelled'),
            'CardNotAcceptedForThisPurchase' => Mage::helper('bankdebit')->__('Your Credit Card not accepted for this purchase')
        );
        $errorMessages = array_change_key_case($errorMessages, CASE_UPPER);

        $errorCode = mb_strtoupper($errorCode);
        return isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : false;
    }

    /**
     * Get Verbose Error Message
     * @param array $details
     * @return string
     */
    public function getVerboseErrorMessage(array $details)
    {
        $errorCode = isset($details['transactionErrorCode']) ? $details['transactionErrorCode'] : $details['errorCode'];
        $errorMessage = $this->getErrorMessageByCode($errorCode);
        if ($errorMessage) {
            return $errorMessage;
        }

        $errorCode = $details['transactionErrorCode'];
        $errorDescription = $details['transactionThirdPartyError'];
        if (empty($errorCode) && empty($errorDescription)) {
            $errorCode = $details['code'];
            $errorDescription = $details['description'];
        }
        return Mage::helper('bankdebit')->__('PayEx error: %s', $errorCode . ' (' . $errorDescription . ')');
    }
}