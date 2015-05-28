<?php
class AAIT_Bankdebit_Block_Banks extends Mage_Core_Block_Abstract implements Mage_Widget_Block_Interface
{
    protected $_options = array();

    /**
     * Get Block Html
     * @return mixed
     */
    protected function _toHtml() {
        $options = $this->getOptions();

        $select = Mage::app()->getLayout()->createBlock('core/html_select')
            ->setName('payexbank')
            ->setId('payexbank')
            ->setValue(null)
            ->setExtraParams(null)
            ->setOptions($options);
        $html = $select->getHtml();
        return $html;
    }

    /**
     * Set Options
     * @param $options
     */
    public function setOptions($options) {
        $this->_options = $options;
    }

    /**
     * Get Options
     * @return array|null
     */
    public function getOptions() {
        if ( count($this->_options) === 0 ) {
            return $this->getAvailableBanks();
        }
        return $this->_options;
    }

    /**
     * Get Available Banks
     * @return array
     */
    public function getAvailableBanks() {
        $selected_banks = Mage::getSingleton('bankdebit/payment')->getConfigData('banks');
        $selected_banks = explode(',', $selected_banks);
        $banks = Mage::getModel('bankdebit/source_banks')->toOptionArray();

        $result = array();
        foreach($banks as $current) {
            if ( in_array($current['value'], $selected_banks) ) {
                $result[] = $current;
            }
        }
        return $result;
    }
}