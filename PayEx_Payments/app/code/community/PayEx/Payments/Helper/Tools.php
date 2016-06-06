<?php

class PayEx_Payments_Helper_Tools extends Mage_Core_Helper_Abstract
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
                $error_message = Mage::helper('payex')->__($message['code']) . ' (' . Mage::helper('payex')->__($message['description']) . ')';

            } else {
                $error_message = Mage::helper('payex')->__('Unknown error');
            }

            if (!empty($message['thirdPartyError'])) {
                $error_message .= ' Third Party Error: ' . Mage::helper('payex')->__($message['thirdPartyError']);
            }
            if (!empty($message['transactionErrorCode']) && !empty($message['transactionErrorDescription'])) {
                $error_message .= ' Transaction Error: ' . Mage::helper('payex')->__($message['transactionErrorCode']) . ' (' . $message['transactionErrorDescription'] . ')';
            }
        } else {
            $error_message = Mage::helper('payex')->__($message);
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
        Mage::log($message, null, 'payment_payex.log');
    }

    /**
     * Get verbose error message by Error Code
     * @param $errorCode
     * @return string | false
     */
    public function getErrorMessageByCode($errorCode)
    {
        $errorMessages = array(
            'REJECTED_BY_ACQUIRER' => Mage::helper('payex')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            //'Error_Generic' => Mage::helper('payex')->__('An unhandled exception occurred'),
            '3DSecureDirectoryServerError' => Mage::helper('payex')->__('A problem with Visa or MasterCards directory server, that communicates transactions for 3D-Secure verification'),
            'AcquirerComunicationError' => Mage::helper('payex')->__('Communication error with the acquiring bank'),
            'AmountNotEqualOrderLinesTotal' => Mage::helper('payex')->__('The sum of your order lines is not equal to the price set in initialize'),
            'CardNotEligible' => Mage::helper('payex')->__('Your customers card is not eligible for this kind of purchase, your customer can contact their bank for more information'),
            'CreditCard_Error' => Mage::helper('payex')->__('Some problem occurred with the credit card, your customer can contact their bank for more information'),
            'PaymentRefusedByFinancialInstitution' => Mage::helper('payex')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Merchant_InvalidAccountNumber' => Mage::helper('payex')->__('The merchant account number sent in on request is invalid'),
            'Merchant_InvalidIpAddress' => Mage::helper('payex')->__('The IP address the request comes from is not registered in PayEx, you can set it up in PayEx Admin under Merchant profile'),
            'Access_MissingAccessProperties' => Mage::helper('payex')->__('The merchant does not have access to requested functionality'),
            'Access_DuplicateRequest' => Mage::helper('payex')->__('Your customers bank declined the transaction, your customer can contact their bank for more information'),
            'Admin_AccountTerminated' => Mage::helper('payex')->__('The merchant account is not active'),
            'Admin_AccountDisabled' => Mage::helper('payex')->__('The merchant account is not active'),
            'ValidationError_AccountLockedOut' => Mage::helper('payex')->__('The merchant account is locked out'),
            'ValidationError_Generic' => Mage::helper('payex')->__('Generic validation error'),
            'ValidationError_HashNotValid' => Mage::helper('payex')->__('The hash on request is not valid, this might be due to the encryption key being incorrect'),
            //'ValidationError_InvalidParameter' => Mage::helper('payex')->__('One of the input parameters has invalid data. See paramName and description for more information'),
            'OperationCancelledbyCustomer' => Mage::helper('payex')->__('The operation was cancelled by the client'),
            'PaymentDeclinedDoToUnspecifiedErr' => Mage::helper('payex')->__('Unexpecter error at 3rd party'),
            'InvalidAmount' => Mage::helper('payex')->__('The amount is not valid for this operation'),
            'NoRecordFound' => Mage::helper('payex')->__('No data found'),
            'OperationNotAllowed' => Mage::helper('payex')->__('The operation is not allowed, transaction is in invalid state'),
            'ACQUIRER_HOST_OFFLINE' => Mage::helper('payex')->__('Could not get in touch with the card issuer'),
            'ARCOT_MERCHANT_PLUGIN_ERROR' => Mage::helper('payex')->__('The card could not be verified'),
            'REJECTED_BY_ACQUIRER_CARD_BLACKLISTED' => Mage::helper('payex')->__('There is a problem with this card'),
            'REJECTED_BY_ACQUIRER_CARD_EXPIRED' => Mage::helper('payex')->__('The card expired'),
            'REJECTED_BY_ACQUIRER_INSUFFICIENT_FUNDS' => Mage::helper('payex')->__('Insufficient funds'),
            'REJECTED_BY_ACQUIRER_INVALID_AMOUNT' => Mage::helper('payex')->__('Incorrect amount'),
            'USER_CANCELED' => Mage::helper('payex')->__('Payment cancelled'),
            'CardNotAcceptedForThisPurchase' => Mage::helper('payex')->__('Your Credit Card not accepted for this purchase'),
            'CreditCheckNotApproved' => Mage::helper('payex')->__('Credit check was declined, please try another payment option')
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

        $errorCode = $details['code'];
        $errorDescription = $details['description'];

        if (!empty($details['transactionErrorCode'])) {
            $errorCode = $details['transactionErrorCode'];
        }

        if (!empty($details['transactionThirdPartyError'])) {
            $errorDescription = $details['transactionThirdPartyError'];
        }

        return Mage::helper('payex')->__('PayEx error: %s', $errorCode . ' (' . $errorDescription . ')');
    }

    /**
     * Get Country Code by SSN
     * @param $ssn
     *
     * @return string|bool
     */
    public function getCountryCodeBySSN($ssn) {
        $rules = array(
            'NO' => '/^[0-9]{6,6}((-[0-9]{5,5})|([0-9]{2,2}((-[0-9]{5,5})|([0-9]{1,1})|([0-9]{3,3})|([0-9]{5,5))))$/',
            'SE' => '/^[0-9]{6,6}(([0-9]{2,2}[-\+]{1,1}[0-9]{4,4})|([-\+]{1,1}[0-9]{4,4})|([0-9]{4,6}))$/',
            //'FI' => '/^[0-9]{6,6}(([A\+-]{1,1}[0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{3,3}[0-9A-FHJK-NPR-Y]{1,1})|([0-9]{1,1}-{0,1}[0-9A-FHJK-NPR-Y]{1,1}))$/i',
            //'DK' => '/^[0-9]{8,8}([0-9]{2,2})?$/',
            //'NL' => '/^[0-9]{7,9}$/'
        );

        foreach ($rules as $country_code => $pattern) {
            if ((bool)preg_match($pattern, $ssn)) {
                return $country_code;
            }
        }

        return false;
    }

    /**
     * Get Name Parser Instance
     * @see https://github.com/joshfraser/PHP-Name-Parser
     * @return FullNameParser
     */
    public function getNameParser()
    {
        if (!class_exists('FullNameParser', false)) {
            require_once Mage::getBaseDir('lib') . '/PHP-Name-Parser/parser.php';
        }

        return new FullNameParser();
    }
}
