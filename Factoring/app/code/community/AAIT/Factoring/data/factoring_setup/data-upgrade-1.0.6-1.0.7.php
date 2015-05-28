<?php
// Get base payment fee
$base_fee = Mage::getSingleton('factoring/fee')->getPaymentFee();

// Update payment fee of orders
$orders = Mage::getModel('sales/order')->getCollection();
$orders->getSelect()->join(
    array('p' => $orders->getResource()->getTable('sales/order_payment')),
    'p.parent_id = main_table.entity_id',
    array()
);
$orders->addFieldToFilter('method','factoring');

foreach ($orders as  $order) {
    $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
    $currentCurrencyCode = $order->getOrderCurrency()->getCurrencyCode();

    $fee = Mage::helper('directory')->currencyConvert($base_fee, $baseCurrencyCode, $currentCurrencyCode);
    $fee = Mage::app()->getStore()->roundPrice($fee);

    $order->setBaseFactoringPaymentFee($base_fee);
    $order->setFactoringPaymentFee($fee);
    $order->save();
}
