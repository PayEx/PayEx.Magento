<?php

class PayEx_Payments_Model_Payment_Agreement extends Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract
    implements Mage_Payment_Model_Billing_Agreement_MethodInterface
{

    /**
     * Billing Agreement Method Code
     */
    const METHOD_BILLING_AGREEMENT = 'payex_billing_agreement';

    /**
     * PayEx Billing Agreement Flag
     */
    const PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT = 'payex_create_ba';

    /**
     * PayEx Billing Agreement Reference
     */
    const PAYMENT_INFO_TRANSPORT_AGREEMENT_REFERENCE = 'payex_agreement_reference';

    /**
     * Agreement Verification Codes
     */
    const AGREEMENT_NOTVERIFIED = 0;
    const AGREEMENT_VERIFIED = 1;
    const AGREEMENT_DELETED = 2;
    const AGREEMENT_NOTEXISTS = 3;

    /**
     * Method code
     * @var string
     */
    protected $_code = self::METHOD_BILLING_AGREEMENT;

    /**
     * Method instance settings
     */
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseCheckout          = false;
    protected $_canUseInternal          = false;
    protected $_isInitializeNeeded      = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canReviewPayment        = false;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'payex/info_billing_agreement';
    protected $_formBlockType = 'payex/form_billing_agreement';

    /**
     * PayEx Payments CC Model
     * @var PayEx_Payments_Model_Payment_CC
     */
    protected $_cc = null;

    /**
     * Initialize PayEx_Payments_Model_Payment_CC model
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->_cc = Mage::helper('payment')->getMethodInstance(PayEx_Payments_Model_Payment_CC::METHOD_CODE);
    }

    /**
     * Store setter
     * Also updates store ID in config object
     * @param Mage_Core_Model_Store|int $store $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        if (null === $store) {
            $store = Mage::app()->getStore()->getId();
        }

        $this->_cc->setStore(!is_object($store) ? Mage::app()->getStore($store) : $store);
        return $this;
    }

    /**
     * Get config action to process initialization
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    /**
     * Check whether payment method can be used
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if (parent::isAvailable($quote) === false) {
            return false;
        }

        return $this->_cc->isAvailable($quote);
    }

    /**
     * Instantiate state and set it to state object
     * @param  $paymentAction
     * @param  $stateObject
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        Mage::helper('payex/tools')->addToDebug('Action: Initialize');

        /** @var Mage_Payment_Model_Info $info */
        $info = $this->getInfoInstance();

        /** @var Mage_Sales_Model_Order $order */
        $order = $info->getOrder();

        $order_id = $order->getIncrementId();

        // Get Currency code
        $currency_code = $order->getOrderCurrency()->getCurrencyCode();

        // Get Operation Type (AUTHORIZATION / SALE)
        $operation = ($this->getConfigData('transactiontype') == 0) ? 'AUTHORIZATION' : 'SALE';

        // Get Amount
        //$amount = $order->getGrandTotal();
        $amount = Mage::helper('payex/order')->getCalculatedOrderAmount($order)->getAmount();

        // Agreement Reference
        $agreement_reference = $info->getAdditionalInformation(self::PAYMENT_INFO_REFERENCE_ID);

        /** @var Mage_Sales_Model_Billing_Agreement $billing_agreement */
        $billing_agreement = Mage::getModel('sales/billing_agreement')->load($agreement_reference, 'reference_id');
        if (!$billing_agreement->getId()) {
            $message = Mage::helper('payex')->__('Wrong Agreement Reference');
            Mage::throwException($message);
            return $this;
        }

        // Verify Agreement Status
        // Call PxAgreement.AgreementCheck
        $params = array(
            'accountNumber' => '',
            'agreementRef' => $billing_agreement->getReferenceId(),
        );
        $result = Mage::helper('payex/api')->getPx()->AgreementCheck($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.AgreementCheck');
        if ($result['code'] !== 'OK' && $result['description'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
            Mage::throwException($message);
            return $this;
        }

        // Check is verified
        if ((int)$result['agreementStatus'] !== self::AGREEMENT_VERIFIED) {
            $message = Mage::helper('payex')->__('This agreement is invalid');
            Mage::throwException($message);
            return $this;
        }

        // Call PxAgreement.AutoPay3
        $params = array(
            'accountNumber' => '',
            'agreementRef' => $billing_agreement->getReferenceId(),
            'price' => round($amount * 100),
            'productNumber' => $order_id,
            'description' => Mage::app()->getStore()->getName(),
            'orderId' => $order_id,
            'purchaseOperation' => $operation,
            'currency' => $currency_code
        );
        $result = Mage::helper('payex/api')->getPx()->AutoPay3($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.AutoPay3');
        if ($result['errorCodeSimple'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
            Mage::throwException($message);
            return $this;
        }

        // Validate transactionStatus value
        if (!isset($result['transactionStatus']) || !is_numeric($result['transactionStatus'])) {
            // AutoPay: No transactionsStatus in response
            Mage::helper('payex/tools')->addToDebug(
                'Error: No transactionsStatus in response.', $order->getIncrementId()
            );
            $message = Mage::helper('payex')->__('Payment failed. Invalid transaction status');
            Mage::throwException($message);
            return $this;
        }

        // Save Order
        $order->save();

        // Register Transaction
        $transaction_id = isset($result['transactionNumber']) ? $result['transactionNumber'] : null;
        $transaction_status = isset($result['transactionStatus']) ? (int)$result['transactionStatus'] : null;

        $order->getPayment()->setTransactionId($transaction_id);
        $transaction = Mage::helper('payex/order')->processPaymentTransaction($order, $result);

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transaction_id)->save();

        // Add Order Relation
        if ($billing_agreement->getId()) {
            //$billing_agreement->addOrderRelation($order->getId())->save();
            $billing_agreement->getResource()->addOrderRelation($billing_agreement->getId(), $order->getId());
        }

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 1:
            case 3:
                // Payment authorized
                $message = Mage::helper('payex')->__('Payment has been authorized');

                // Change order status
                $new_status = $this->_cc->getConfigData('order_status_authorize');

                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('payex/order')->getAssignedStatus($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Set state object
                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('payex/order')->getAssignedStatus($new_status);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);
                break;
            case 0:
            case 6:
                // Payment captured
                $message = Mage::helper('payex')->__('Payment has been captured');

                // Change order status
                $new_status = $this->_cc->getConfigData('order_status_capture');

                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('payex/order')->getAssignedStatus($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Set state object
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                // Create Invoice for Sale Transaction
                $invoice = Mage::helper('payex/order')->makeInvoice($order, false);
                $invoice->setTransactionId($result['transactionNumber']);
                $invoice->save();
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = Mage::helper('payex')->__(
                        'Detected an abnormal payment process (Transaction Status: %s).',
                        $transaction_status
                    );
                } elseif ($transaction_status === 4) {
                    $message = Mage::helper('payex')->__('Order automatically canceled.');
                } else {
                    $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
                }

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('payex/order')->getAssignedStatus(Mage_Sales_Model_Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                Mage::throwException($message);
                break;
            default:
                // Invalid transaction status
                $message = Mage::helper('payex')->__('Invalid transaction status.');

                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Set state object
                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('payex/order')->getAssignedStatus(Mage_Sales_Model_Order::STATE_CANCELED);
                $stateObject->setState($status->getState());
                $stateObject->setStatus($status->getStatus());
                $stateObject->setIsNotified(true);

                Mage::throwException($message);
                break;
        }

        return $this;
    }

    /**
     * Init billing agreement
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function initBillingAgreementToken(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        return $this;
    }

    /**
     * Retrieve billing agreement customer details by token
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return array
     */
    public function getBillingAgreementTokenInfo(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        // Verify Agreement Status
        // Call PxAgreement.AgreementCheck
        $params = array(
            'accountNumber' => '',
            'agreementRef' => $agreement->getReferenceId(),
        );
        $result = Mage::helper('payex/api')->getPx()->AgreementCheck($params);
        Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.AgreementCheck');
        if ($result['code'] !== 'OK' && $result['description'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
            Mage::throwException($message);
            return $this;
        }

        $responseData = array(
            'agreement_status' => (int)$result['agreementStatus'],
        );
        $agreement->addData($responseData);
        return $responseData;
    }

    /**
     * Create billing agreement by token specified in request
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function placeBillingAgreement(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        return $this;
    }

    /**
     * Update billing agreement status
     * @param Mage_Payment_Model_Billing_AgreementAbstract $agreement
     * @return $this
     */
    public function updateBillingAgreementStatus(Mage_Payment_Model_Billing_AgreementAbstract $agreement)
    {
        switch ($agreement->getStatus()) {
            case (Mage_Sales_Model_Billing_Agreement::STATUS_CANCELED):
                // Call PxAgreement.DeleteAgreement
                $params = array(
                    'accountNumber' => '',
                    'agreementRef' => $agreement->getReferenceId(),
                );

                $result = Mage::helper('payex/api')->getPx()->DeleteAgreement($params);
                Mage::helper('payex/tools')->debugApi($result, 'PxAgreement.DeleteAgreement');
                if ($result['code'] !== 'OK' || $result['description'] !== 'OK') {
                    $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
                    Mage::throwException($message);
                }
                break;
            default:
                // no break
        }

        return $this;
    }

    /**
     * Capture payment
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function capture(Varien_Object $payment, $amount)
    {
        return $this->_cc->capture($payment, $amount);
    }

    /**
     * Cancel payment
     * @param   Varien_Object $payment
     * @return  $this
     */
    public function cancel(Varien_Object $payment)
    {
        return $this->_cc->cancel($payment);
    }

    /**
     * Refund capture
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        return $this->_cc->refund($payment, $amount);
    }

    /**
     * Void payment
     * @param Varien_Object $payment
     * @return $this
     */
    public function void(Varien_Object $payment)
    {
        return $this->_cc->void($payment);
    }

    /**
     * Fetch transaction details info
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        return $this->_cc->fetchTransactionInfo($payment, $transactionId);
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    protected function _isAvailable($quote)
    {
        return true;
    }

}
