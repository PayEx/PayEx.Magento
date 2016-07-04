<?php
$installer = $this;
$this->startSetup();

$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee_invoiced', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee_tax_invoiced', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee_invoiced', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee_tax_invoiced', 'decimal(12,4)');

$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee_refunded', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'payex_payment_fee_tax_refunded', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee_refunded', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_payex_payment_fee_tax_refunded', 'decimal(12,4)');

$this->_conn->addColumn($this->getTable('sales_flat_invoice'), 'payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_invoice'), 'payex_payment_fee_tax', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_invoice'), 'base_payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_invoice'), 'base_payex_payment_fee_tax', 'decimal(12,4)');

$this->_conn->addColumn($this->getTable('sales_flat_creditmemo'), 'payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_creditmemo'), 'payex_payment_fee_tax', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_creditmemo'), 'base_payex_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_creditmemo'), 'base_payex_payment_fee_tax', 'decimal(12,4)');

$eav = new Mage_Sales_Model_Resource_Setup('sales_setup');
$eav->addAttribute('order', 'payex_payment_fee_invoiced', array('type' => 'decimal'));
$eav->addAttribute('order', 'payex_payment_fee_tax_invoiced', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee_invoiced', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee_tax_invoiced', array('type' => 'decimal'));

$eav->addAttribute('order', 'payex_payment_fee_refunded', array('type' => 'decimal'));
$eav->addAttribute('order', 'payex_payment_fee_tax_refunded', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee_refunded', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_payex_payment_fee_tax_refunded', array('type' => 'decimal'));

$eav->addAttribute('invoice', 'payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('invoice', 'payex_payment_fee_tax', array('type' => 'decimal'));
$eav->addAttribute('invoice', 'base_payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('invoice', 'base_payex_payment_fee_tax', array('type' => 'decimal'));

$eav->addAttribute('creditmemo', 'payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('creditmemo', 'payex_payment_fee_tax', array('type' => 'decimal'));
$eav->addAttribute('creditmemo', 'base_payex_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('creditmemo', 'base_payex_payment_fee_tax', array('type' => 'decimal'));

$this->endSetup();
