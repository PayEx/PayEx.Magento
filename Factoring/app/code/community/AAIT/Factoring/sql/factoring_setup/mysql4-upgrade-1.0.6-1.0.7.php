<?php

$this->startSetup();

$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'factoring_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_quote'), 'base_factoring_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'factoring_payment_fee', 'decimal(12,4)');
$this->_conn->addColumn($this->getTable('sales_flat_order'), 'base_factoring_payment_fee', 'decimal(12,4)');

$eav = new Mage_Sales_Model_Resource_Setup('sales_setup');
$eav->addAttribute('quote', 'factoring_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('quote', 'base_factoring_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('order', 'factoring_payment_fee', array('type' => 'decimal'));
$eav->addAttribute('order', 'base_factoring_payment_fee', array('type' => 'decimal'));

$this->endSetup();
