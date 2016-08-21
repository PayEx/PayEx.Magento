<?php

class PayEx_Payments_Block_Form_Billing_Agreement extends Mage_Payment_Block_Form
{
    /**
     * Set custom template
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('payex/billing/agreement/form.phtml');
        $this->setTransportName(Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::TRANSPORT_BILLING_AGREEMENT_ID);
    }

    /**
     * Retrieve available customer billing agreements
     *
     * @return array
     */
    public function getBillingAgreements()
    {
        $data = array();
        $quote = $this->getParentBlock()->getQuote();
        if (!$quote || !$quote->getCustomer()) {
            return $data;
        }
        $collection = Mage::getModel('sales/billing_agreement')->getAvailableCustomerBillingAgreements(
            $quote->getCustomer()->getId()
        );

        foreach ($collection as $item) {
            $data[$item->getId()] = $item->getAgreementLabel();
        }
        return $data;
    }
}
