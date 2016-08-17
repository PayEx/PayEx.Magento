<?php

class PayEx_Payments_Model_Invoice_Pdf_Total extends Mage_Sales_Model_Order_Pdf_Total_Default
{
    /**
     * Get array of arrays with totals information for display in PDF
     * array(
     *  $index => array(
     *      'amount'   => $amount,
     *      'label'    => $label,
     *      'font_size'=> $font_size
     *  )
     * )
     * @return array
     */
    public function getTotalsForDisplay()
    {
        $fontSize      = $this->getFontSize() ? $this->getFontSize() : 7;
        $amountInclTax = $this->getAmount() + $this->getOrder()->getPayexPaymentFeeTax();
        $amountExclTax = $this->getAmount();

        if ($this->displaySalesPayExFeeBoth()) {
            $totals = array(
                array(
                    'amount'    => $this->getAmountPrefix() . $this->getOrder()->formatPriceTxt($amountInclTax) ,
                    'label'     => Mage::helper('payex')->__('Payment fee (Incl.Tax)') . ':',
                    'font_size' => $fontSize
                ),
                array(
                    'amount'    => $this->getAmountPrefix() . $this->getOrder()->formatPriceTxt($amountExclTax),
                    'label'     => Mage::helper('payex')->__('Payment fee (Excl.Tax)') . ':',
                    'font_size' => $fontSize
                )
            );
        } elseif ($this->displaySalesPayExFeeInclTax()) {
            $totals = array(
                array(
                    'amount'    => $this->getAmountPrefix() . $this->getOrder()->formatPriceTxt($amountInclTax) ,
                    'label'     => Mage::helper('payex')->__('Payment fee') . ':',
                    'font_size' => $fontSize
                )
            );
        } else {
            $totals = array(
                array(
                    'amount'    => $this->getAmountPrefix() . $this->getOrder()->formatPriceTxt($amountExclTax),
                    'label'     => Mage::helper('payex')->__('Payment fee') . ':',
                    'font_size' => $fontSize
                )
            );
        }

        return $totals;
    }

    /**
     * Check if we can display total information in PDF
     * @return bool
     */
    public function canDisplay()
    {
        $amount = $this->getAmount();
        return ($this->getDisplayZero() || ($amount != 0));
    }

    /**
     * Get Total amount from source
     * @return float
     */
    public function getAmount()
    {
        $fee = $this->getOrder()->getPayexPaymentFee();
        if (!$fee){
            return 0;
        }

        return $fee;
    }

    /**
     * Check if display cart prices fee included and excluded tax
     * @return mixed
     */
    public function displaySalesPayExFeeBoth()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displaySalesPayExFeeBoth($this->getOrder()->getStore());
    }

    /**
     * Check if display cart prices fee included tax
     * @return mixed
     */
    public function displaySalesPayExFeeInclTax()
    {
        $config = Mage::getSingleton('payex/fee_config');
        return $config->displaySalesPayExFeeInclTax($this->getOrder()->getStore());
    }
}
