<?php

class PayEx_Payments_Block_Adminhtml_Config_Hint extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    protected $_template = 'payex/adminhtml/hint.phtml';

    /**
     * Render fieldset html
     * @param Varien_Data_Form_Element_Abstract $element element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $current_version = Mage::getConfig()->getModuleConfig('PayEx_Payments')->version->asArray();
        $last_version = $current_version;

        // Load cached available versions info
        $availableVersions = Mage::app()->loadCache('payex_available_versions');
        $availableVersions = $availableVersions ? @unserialize($availableVersions) : array();
        if (isset($availableVersions['PayEx_Payments'])) {
            $last_version = $availableVersions['PayEx_Payments']['version'];
        }

        $this->assign('current_version', $current_version);
        $this->assign('last_version', $last_version);
        Mage::getSingleton('adminhtml/session')->setIsPayexHintShowed(true);
        return $this->toHtml();
    }
}
