<?php

class PayEx_Payments_Model_Feed extends Mage_AdminNotification_Model_Feed
{
    const URL_NEWS = 'http://payex.aait.se/application/meta/check?key=V004t905i8O171l';

    /**
     * Check Updates
     * @return mixed
     */
    public static function check()
    {
        return Mage::getModel('payex/feed')->checkUpdate();
    }

    /**
     * Check Updates
     * @return $this
     */
    public function checkUpdate()
    {
        if (($this->getFrequency() + $this->getLastUpdate()) > time()) {
            return $this;
        }

        $this->setLastUpdate();

        // cURL extension required to get feed
        if (!extension_loaded('curl')) {
            return $this;
        }

        $feedData = array();
        $availableVersions = array();

        // Get Notifications
        $this->_feedUrl = $this->getFeedUrl();
        $feedXml = $this->getFeedData();
        if ($feedXml && $feedXml->channel && $feedXml->channel->item) {
            foreach ($feedXml->channel->item as $item) {
                // is Version Notification
                if ($item->code && $item->version) {
                    $code = (string) $item->code;
                    $version = (string) $item->version;

                    if (empty($availableVersions[$code])
                        || version_compare($version, $availableVersions[$code]['version'], '>'))
                    {
                        $availableVersions[$code] = array(
                            'code' => $code,
                            'version' => $version,
                            'title' => (string)$item->title,
                            'description' => (string)$item->description,
                            'url' => (string)$item->link,
                        );
                    }

                    continue;
                }

                // is Generic Notification
                $feedData[] = array(
                    'severity'      => 3,
                    'date_added'    => $this->getDate((string) $item->date),
                    'title'         => (string)$item->title,
                    'description'   => (string)$item->description,
                    'url'           => (string)$item->link,
                );
            }

            if (count($feedData) > 0) {
                $inbox = Mage::getModel('adminnotification/inbox');

                if ($inbox) {
                    $inbox->parse($feedData);
                }
            }

            if (count($availableVersions) > 0) {
                Mage::app()->saveCache(serialize($availableVersions), 'payex_available_versions');
            }
        }

        return $this;
    }

    public function getFrequency()
    {
        /**
         * if adminnotification is disabled, parent::getFrequency() returns 0
         * resulting in a new request on every admin page reload
         */
        if(parent::getFrequency()) return parent::getFrequency();

        return 24*3600;
    }

    /**
     * Get Last Update time
     * @return mixed
     */
    public function getLastUpdate()
    {
        return Mage::app()->loadCache('payex_notifications_last_check');
    }

    /**
     * Set Last Update time
     * @return $this
     */
    public function setLastUpdate()
    {
        Mage::app()->saveCache(time(), 'payex_notifications_last_check');
        return $this;
    }

    /**
     * Get Feed URL
     * @return string
     * @throws Zend_Uri_Exception
     */
    public function getFeedUrl()
    {
        $version = Mage::getConfig()->getModuleConfig('PayEx_Payments')->version->asArray();
        $params = array(
            'site_url' => Mage::getStoreConfig('web/unsecure/base_url'),
            'installed_version' => $version,
            'mage_ver' => Mage::getVersion(),
            'edition' => Mage::getEdition()
        );

        $uri = Zend_Uri::factory(self::URL_NEWS);
        $uri->addReplaceQueryParameters($params);
        return $uri->getUri();
    }
}