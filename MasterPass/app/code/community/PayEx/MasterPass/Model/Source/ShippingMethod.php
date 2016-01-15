<?php

class PayEx_MasterPass_Model_Source_ShippingMethod
{
    public function toOptionArray()
    {
        $methods = array(
            array(
                'value' => '',
                'label' => Mage::helper('adminhtml')->__('--Please Select--')
            )
        );

        $activeCarriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $options = array();

            $carrierTitle = sprintf('Carrier "%s"', $carrierCode);
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = array('value' => $code, 'label' => $method);

                }
                $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title');
            }

            $methods[] = array('value' => $options, 'label' => $carrierTitle);
        }

        return $methods;
    }
}
