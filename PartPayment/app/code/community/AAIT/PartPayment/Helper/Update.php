<?php

class AAIT_PartPayment_Helper_Update extends Mage_Core_Helper_Abstract
{
    const VERSION_CHECK_URL = 'http://shop.aait.se';

    public function getAvailableVersions()
    {
        $cache = Mage::app()->getCache();
        $client = $client = new Zend_Http_Client();
        if (extension_loaded('curl')) {
            $client->setAdapter('Zend_Http_Client_Adapter_Curl');
        }

        $modules_list = array();
        $modules = Mage::getConfig()->getNode('modules')->children();
        /** @var $config Mage_Core_Model_Config_Element */
        foreach ($modules as $module => $config) {
            if ($config->is('active') && mb_strpos($module, 'AAIT_') !== false) {
                // Check version
                $current_version = (string) $config->version;
                $last_version = $cache->load($module . '_version_check');

                if (!$last_version) {
                    $last_version = $current_version;

                    $url = self::VERSION_CHECK_URL . '/Changelog_' . mb_strtolower(str_replace('AAIT_', '', $module)) . '.txt';
                    $client->setUri($url);

                    try {
                        $response = $client->request(Zend_Http_Client::GET);
                        if ($response->isSuccessful()) {
                            $change_log = explode("\n", $response->getBody());
                            // Search last version
                            foreach ($change_log as $log) {
                                if (mb_strpos($log, 'Version') !== false) {
                                    $last_version = str_replace(array('Version', ' '), '', trim($log));
                                    $cache->save($last_version, $module . '_version_check', array(), 7);
                                    break;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        //
                    }
                }

                $modules_list[ucfirst(str_replace('AAIT_', '', $module))] = array(
                    'current_version' => $current_version,
                    'last_version' => $last_version
                );
            }
        }

        return $modules_list;
    }
}