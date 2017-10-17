<?php
/**
 * This script perform follow actions:
 * 1) Move SSN settings to another place
 */

/** @var Mage_Core_Model_Resource $resource */
$resource = Mage::getSingleton('core/resource');
$writeConnection = $resource->getConnection('core_write');
$readConnection = $resource->getConnection('core_read');

try {
    $core_config_data_table = $resource->getTableName('core_config_data');
    $select = $readConnection->select()
                             ->from($core_config_data_table)
                             ->where('path = ?', 'payex_ssn/payex_ssn/active');

    $row = $readConnection->fetchRow($select);
    if ($row) {
        $scope = $row['scope'];
        $scope_id = $row['scope_id'];
        $value = $row['value'];
        $path = 'payment/payex_financing/checkout_field';
        $writeConnection->query("INSERT IGNORE INTO `{$core_config_data_table}` (scope, scope_id, path, value) VALUES('{$scope}', '{$scope_id}', '{$path}', '{$value}');");
    }
} catch (Exception $e) {
    Mage::logException($e);
}
