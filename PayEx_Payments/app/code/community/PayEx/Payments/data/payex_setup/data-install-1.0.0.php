<?php
$resource = Mage::getSingleton('core/resource');
$writeConnection = $resource->getConnection('core_write');
$readConnection = $resource->getConnection('core_read');

// Upgrade orders to new payment method names
$sales_flat_order_payment_table = $resource->getTableName('sales_flat_order_payment');
$sales_flat_quote_payment_table = $resource->getTableName('sales_flat_quote_payment');
$migrate = array(
    'payexautopay' => 'payex_autopay',
    'bankdebit' => 'payex_bankdebit',
    'payex2' => 'payex_cc',
    'factoring' => 'payex_financing',
    'payexinvoice' => 'payex_invoice',
    'payex_mp' => 'payex_masterpass',
    'partpayment' => 'payex_partpayment',
    'wywallet' => 'payex_wywallet',
);
foreach ($migrate as $old_name => $new_name) {
    $writeConnection->query("UPDATE `{$sales_flat_order_payment_table}` SET method = '{$new_name}' WHERE method = '{$old_name}';");
    $writeConnection->query("UPDATE `{$sales_flat_quote_payment_table}` SET method = '{$new_name}' WHERE method = '{$old_name}';");
}

// Update payment fee of orders
$orders = Mage::getModel('sales/order')->getCollection();
$orders->getSelect()->join(
    array('p' => $orders->getResource()->getTable('sales/order_payment')),
    'p.parent_id = main_table.entity_id',
    array()
);
$orders->addFieldToFilter('method', array('in' => array('payex_financing', 'payex_invoice', 'payex_partpayment')));
foreach ($orders as $order) {
    try {
        $method = $order->getPayment()->getMethodInstance()->getCode();
    } catch (Exception $e) {
        continue;
    }

    switch ($method) {
        case 'payex_invoice':
            $order->setBasePayexPaymentFee($order->getBasePayexinvoicePaymentFee());
            $order->setPayexPaymentFee($order->getPayexinvoicePaymentFee());
            $order->save();

            $quote = Mage::getModel('sales/quote')->setStore($order->getStore())->load($order->getQuoteId());
            if ($quote) {
                $quote->setBasePayexPaymentFee($quote->getBasePayexinvoicePaymentFee());
                $quote->setPayexPaymentFee($quote->getPayexinvoicePaymentFee());
                $quote->save();
            }
            break;
        case 'payex_financing':
            $order->setBasePayexPaymentFee($order->getBaseFactoringPaymentFee());
            $order->setPayexPaymentFee($order->getFactoringPaymentFee());
            $order->save();

            $quote = Mage::getModel('sales/quote')->setStore($order->getStore())->load($order->getQuoteId());
            if ($quote) {
                $quote->setBasePayexPaymentFee($quote->getBaseFactoringPaymentFee());
                $quote->setPayexPaymentFee($quote->getFactoringPaymentFee());
                $quote->save();
            }
            break;
        case 'payex_partpayment':
            $order->setBasePayexPaymentFee($order->getBasePartpaymentPaymentFee());
            $order->setPayexPaymentFee($order->getPartpaymentPaymentFee());
            $order->save();

            $quote = Mage::getModel('sales/quote')->setStore($order->getStore())->load($order->getQuoteId());
            if ($quote) {
                $quote->setBasePayexPaymentFee($quote->getBasePartpaymentPaymentFee());
                $quote->setPayexPaymentFee($quote->getPartpaymentPaymentFee());
                $quote->save();
            }
            break;
    }
}

// Migrate payment configuration
$migrate = array(
    'payment/payexautopay' => 'payment/payex_autopay',
    'payment/bankdebit' => 'payment/payex_bankdebit',
    'payment/payex2' => 'payment/payex_cc',
    'payment/factoring' => 'payment/payex_financing',
    'payment/payexinvoice' => 'payment/payex_invoice',
    'payment/payex_mp' => 'payment/payex_masterpass',
    'payment/partpayment' => 'payment/payex_partpayment',
    'payment/wywallet' => 'payment/payex_wywallet',
    'aait_ssn/aait_ssn' => 'payex_ssn/payex_ssn',
);

foreach ($migrate as $old_name => $new_name) {
    $core_config_data_table = $resource->getTableName('core_config_data');
    $results = $readConnection->fetchAll("SELECT scope, scope_id, path, value FROM `{$core_config_data_table}` WHERE path LIKE '{$old_name}/%';");
    if (count($results) > 0) {
        foreach($results as $id => $row) {
            $scope = $row['scope'];
            $scope_id = $row['scope_id'];
            $value = $row['value'];
            $path = str_replace($old_name, $new_name, $row['path']);
            $writeConnection->query("INSERT IGNORE INTO `{$core_config_data_table}` (scope, scope_id, path, value) VALUES('{$scope}', '{$scope_id}', '{$path}', '{$value}');");
        }
    }
}

// Disable old modules
$modules_list = array(
    'AAIT_Bankdebit', 'AAIT_Factoring', 'AAIT_PartPayment', 'AAIT_Payex2', 'AAIT_Payexapi',
    'AAIT_Payexautopay', 'AAIT_Payexinvoice', 'AAIT_Wywallet', 'PayEx_MasterPass',  'PayEx_MasterPass', 'AAIT_SocialSecurityNumber'
);
$modules_dir = Mage::getModel('core/config')->getOptions()->getEtcDir() . DS . 'modules';
foreach ($modules_list as $id => $module_name) {
    $module_file = $modules_dir . DS . $module_name . '.xml';
    if (file_exists($module_file)) {
        @rename($module_file, $module_file . '.bak');
    }
}
