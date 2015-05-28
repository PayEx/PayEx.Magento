<?php
file_put_contents('/tmp/123.txt', 'upgrade');

$installer = $this;
$installer->startSetup();

$installer->run("
CREATE TABLE IF NOT EXISTS `{$this->getTable('payexautopay_agreement')}` (
  `agreement_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Agreement Reference',
  `customer_id` int(10) NOT NULL COMMENT 'Customer Id',
  `agreement_ref` varchar(255) NOT NULL COMMENT 'Agreement Reference',
  `created_at` datetime NOT NULL COMMENT 'Date',
  PRIMARY KEY (`agreement_id`),
  UNIQUE KEY `agreement_ref` (`agreement_ref`),
  UNIQUE KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Payex Autopay: Agreements';
");
$installer->endSetup();
