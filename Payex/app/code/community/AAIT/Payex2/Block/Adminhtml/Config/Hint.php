<?php

class AAIT_Payex2_Block_Adminhtml_Config_Hint extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{

    protected $_template = 'payex2/hint.phtml';

    /**
     * Render fieldset html
     * @param Varien_Data_Form_Element_Abstract $element element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        // Load cached available versions info
        $availableVersions = Mage::app()->loadCache('payex_available_versions');
        $availableVersions = $availableVersions ? @unserialize($availableVersions) : array();

        // Prepare modules info
        $result = array();
        $modules = Mage::getModel('payex2/feed')->getInstalledPayExModules();
        foreach ($modules as $module => $current_version) {
            $name = ucfirst(str_replace('AAIT_', '', $module));
            $result[$name] = array(
                'current_version' => $current_version,
                'last_version' => isset($availableVersions[$module]) ? $availableVersions[$module]['version'] : $current_version
            );
        }

        $this->assign('modules',  $result);
        Mage::getSingleton('adminhtml/session')->setIsPayexHintShowed(true);
        return $this->toHtml();
    }
}
