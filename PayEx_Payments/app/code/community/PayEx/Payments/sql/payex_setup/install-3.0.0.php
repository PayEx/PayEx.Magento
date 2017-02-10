<?php
$installer = $this;
$this->startSetup();

// Install fee
$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'payex_payment_fee_tax', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'base_payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'base_payex_payment_fee_tax', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee_tax', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee_tax', 'decimal(12,4)');

$eav = new Mage_Sales_Model_Resource_Setup('sales_setup');
$eav->addAttribute('quote', 'payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('quote', 'payex_payment_fee_tax', array('type' => 'decimal'));
$eav->addAttribute('quote', 'base_payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('quote', 'base_payex_payment_fee_tax', array('type' => 'decimal'));
$eav->addAttribute('order', 'payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('order', 'payex_payment_fee_tax', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee_tax', array('type' => 'decimal'));

// Install Agreement table
$installer->run(
    "
CREATE TABLE IF NOT EXISTS `{$this->getTable('payexautopay_agreement')}` (
  `agreement_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Agreement Reference',
  `customer_id` int(10) NOT NULL COMMENT 'Customer Id',
  `agreement_ref` varchar(255) NOT NULL COMMENT 'Agreement Reference',
  `created_at` datetime NOT NULL COMMENT 'Date',
  PRIMARY KEY (`agreement_id`),
  UNIQUE KEY `agreement_ref` (`agreement_ref`),
  UNIQUE KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Payex Autopay: Agreements';
"
);

$this->endSetup();
