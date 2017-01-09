<?php
/**
 * This script perform follow actions:
 * 1) Create Billing Agreements using AutoPay References
 * 2) Convert AutoPay orders to Billing Agreement orders
 */

$resource = Mage::getSingleton('core/resource');
$writeConnection = $resource->getConnection('core_write');
$readConnection = $resource->getConnection('core_read');

// Convert Agreements to PayEx Billing Agreement
$default_label = Mage::helper('payex')->__('Untitled Credit Card');
$payexautopay_agreement_table = $resource->getTableName('payexautopay_agreement');
if ($writeConnection->isTableExists(trim($payexautopay_agreement_table, '`'))) {
    $records = $readConnection->fetchAll("SELECT * FROM {$payexautopay_agreement_table};");
    foreach ($records as $record) {
        $customer_id = $record['customer_id'];
        $agreement_ref = $record['agreement_ref'];
        $created_at = $record['created_at'];

        // Skip empty lines
        if (empty($agreement_ref)) {
            continue;
        }

        // Check Billing Agreement is already exists
        /** @var Mage_Sales_Model_Billing_Agreement $billing_agreement */
        $billing_agreement = Mage::getModel('sales/billing_agreement')->load($agreement_ref, 'reference_id');
        if ($billing_agreement->getId()) {
            continue;
        }

        // Create Billing Agreement
        $billing_agreement = Mage::getModel('sales/billing_agreement');
        $billing_agreement->setCustomerId($customer_id)
            ->setMethodCode(PayEx_Payments_Model_Payment_Agreement::METHOD_BILLING_AGREEMENT)
            ->setReferenceId($agreement_ref)
            ->setStatus(Mage_Sales_Model_Billing_Agreement::STATUS_ACTIVE)
            ->setCreatedAt($created_at)
            ->setAgreementLabel($default_label)
            ->save();
    }

    // Update AutoPay Orders to use Billing Agreement
    $orders = Mage::getModel('sales/order')->getCollection();
    $orders->getSelect()->join(
        array('p' => $orders->getResource()->getTable('sales/order_payment')),
        'p.parent_id = main_table.entity_id',
        array()
    );
    $orders->addFieldToFilter('method', 'payex_autopay');
    foreach ($orders as $order) {
        /** @var Mage_Sales_Model_Order $order */
        $customer_id = (int) $order->getCustomerId();
        if ($customer_id > 0) {
            $payexautopay_agreement_table = $resource->getTableName('payexautopay_agreement');
            $agreement_ref = $readConnection->fetchOne("SELECT agreement_ref FROM {$payexautopay_agreement_table} WHERE customer_id = {$customer_id} LIMIT 1;");

            /** @var Mage_Sales_Model_Billing_Agreement $billing_agreement */
            $billing_agreement = Mage::getModel('sales/billing_agreement')->load($agreement_ref, 'reference_id');
            if ($billing_agreement->getId()) {
                // Add Order Relation
                //$billing_agreement->addOrderRelation($order->getId())->save();
                $billing_agreement->getResource()->addOrderRelation($billing_agreement->getId(), $order->getId());

                // Set Agreement Reference
                try {
                    $order->getPayment()->setAdditionalInformation(
                        Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::TRANSPORT_BILLING_AGREEMENT_ID,
                        $billing_agreement->getId()
                    );
                    $order->getPayment()->setAdditionalInformation(
                        Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::PAYMENT_INFO_REFERENCE_ID,
                        $billing_agreement->getReferenceId()
                    );
                    $order->getPayment()->save();
                } catch (Exception $e) {
                    continue;
                }

                // Update Quote Payment
                $quoteId = $order->getQuoteId();

                /** @var Mage_Sales_Model_Quote $quote */
                $quote = Mage::getModel('sales/quote')->setStore($order->getStore())->load($quoteId);
                if ($quote) {
                    // Set Agreement Reference for Quote
                    try {
                        $quote->getPayment()->setAdditionalInformation(
                            Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::TRANSPORT_BILLING_AGREEMENT_ID,
                            $billing_agreement->getId()
                        );
                        $quote->getPayment()->setAdditionalInformation(
                            Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::PAYMENT_INFO_REFERENCE_ID,
                            $billing_agreement->getReferenceId()
                        );
                        $quote->getPayment()->save();
                    } catch (Exception $e) {
                        continue;
                    }
                }

                // Try to set Agreement Label
                if (in_array($billing_agreement->getAgreementLabel(), array('', $default_label))) {
                    if ($transactionId = $order->getPayment()->getLastTransId()) {
                        if ($transaction = $order->getPayment()->getTransaction($transactionId)) {
                            $transaction_data = $transaction->getAdditionalInformation(
                                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
                            );
                            if (is_array($transaction_data)) {
                                if (!empty($transaction_data['maskedNumber']) || $transaction_data['maskedCard']) {
                                    $masked_number = Mage::helper('payex/order')->getFormattedCC($transaction_data);
                                    $billing_agreement->setAgreementLabel($masked_number)->save();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Convert AutoPay orders to Billing Agreement orders
$method_code = PayEx_Payments_Model_Payment_Agreement::METHOD_BILLING_AGREEMENT;
$sales_flat_order_payment_table = $resource->getTableName('sales_flat_order_payment');
$sales_flat_quote_payment_table = $resource->getTableName('sales_flat_quote_payment');
$writeConnection->query("UPDATE `{$sales_flat_order_payment_table}` SET method = '{$method_code}' WHERE method = 'payex_autopay';");
$writeConnection->query("UPDATE `{$sales_flat_quote_payment_table}` SET method = '{$method_code}' WHERE method = 'payex_autopay';");
