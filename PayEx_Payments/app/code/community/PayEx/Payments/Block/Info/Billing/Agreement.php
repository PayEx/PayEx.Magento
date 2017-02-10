<?php

class PayEx_Payments_Block_Info_Billing_Agreement extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payex/billing/agreement/info.phtml');
    }

    /**
     * Add reference id to payment method information
     * @param null $transport
     * @return null|Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }

        $info = $this->getInfo();
        $referenceID = $info->getAdditionalInformation(
            Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::PAYMENT_INFO_REFERENCE_ID
        );
        $transport = new Varien_Object(
            array(
            $this->__('Reference ID') => $referenceID,
            )
        );
        if (!empty($referenceID)) {
            /** @var Mage_Sales_Model_Billing_Agreement $billing_agreement */
            $billing_agreement = Mage::getModel('sales/billing_agreement')->load($referenceID, 'reference_id');
            if ($billing_agreement->getId()) {
                $transport->setData($this->__('Payment Method'), $billing_agreement->getAgreementLabel());
            }
        }

        $transport = parent::_prepareSpecificInformation($transport);

        return $transport;
    }
}
