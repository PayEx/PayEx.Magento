<?php

class AAIT_Factoring_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('factoring/info.phtml');
        // Template for Checkout page
        if ($this->getRequest()->getRequestedActionName() === 'progress') {
            $this->setTemplate('factoring/title.phtml');
        }

    }

    /**
     * Returns code of payment method
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getSpecificInformation()
    {
        // Get Payment Info
        $_info = $this->getInfo();

        // Transaction Fields
        $fields = array(
            'PayEx Payment Method' => array('paymentMethod', 'cardProduct'),
            //'Masked Number' => array('maskedNumber', 'maskedCard'),
            //'Bank Hash' => array('BankHash', 'csId', 'panId'),
            'Bank Reference' => array('bankReference'),
            'Authenticated Status' => array('AuthenticatedStatus', 'authenticatedStatus'),
            'Transaction Ref' => array('transactionRef'),
            'PayEx Transaction Number' => array('transactionNumber'),
            'PayEx Transaction Status' => array('transactionStatus'),
            'Transaction Error Code' => array('transactionErrorCode'),
            'Transaction Error Description' => array('transactionErrorDescription'),
            'Transaction ThirdParty Error' => array('transactionThirdPartyError')
        );

        if ($_info) {
            $transactionId = $_info->getLastTransId();

            if ($transactionId) {
                // Load transaction
                $transaction = $_info->getTransaction($transactionId);
                if ($transaction) {
                    $transaction_data = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
                    if (!$transaction_data) {
                        $payment = $_info->getOrder()->getPayment();
                        $transaction_data = $payment->getMethodInstance()->fetchTransactionInfo($payment, $transactionId);
                        $transaction->setAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $transaction_data);
                        $transaction->save();
                    }

                    $result = array();
                    foreach ($fields as $description => $list) {
                        foreach ($list as $key => $value) {
                            if (!empty($transaction_data[$value])) {
                                $result[$description] = $transaction_data[$value];
                                break;
                            }
                        }
                    }

                    return $result;
                }
            }
        }

        // @todo Info in email when invoicing
        return $this->_prepareSpecificInformation()->getData();
    }

    /**
     * Build PDF content of info block
     *
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('factoring/pdf/info.phtml');
        return $this->toHtml();
    }
}
