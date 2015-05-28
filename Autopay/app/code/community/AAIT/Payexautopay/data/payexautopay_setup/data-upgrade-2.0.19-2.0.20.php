<?php
$records = $this->_conn->fetchAll("SELECT * FROM payex_autopay;");
foreach ($records as $record) {
    $agreement = Mage::getModel('payexautopay/agreement');
    $agreement->setCustomerId($record['customer_id'])
        ->setAgreementRef($record['agreement_id'])
        ->setCreatedAt(date('Y-m-d H:i:s', time()))
        ->save();
}
