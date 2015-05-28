<?php
require_once(Mage::getBaseDir('lib') . '/Px/Px.php');

class AAIT_SocialSecurityNumber_GetaddrController extends Mage_Core_Controller_Front_Action
{
    const XML_PATH_MODULE_DEBUG = 'aait_ssn/aait_ssn/debug';
    const XML_PATH_MODULE_ACCOUNTNUMBER = 'aait_ssn/aait_ssn/accountnumber';
    const XML_PATH_MODULE_ENCRYPTIONKEY = 'aait_ssn/aait_ssn/encryptionkey';

    public function indexAction()
    {
        // Get initial data from request
        $ssn = $this->getRequest()->getParam('ssn');

        // Init PayEx
        $px = new Px();
        $px->setEnvironment(Mage::getStoreConfig(self::XML_PATH_MODULE_ACCOUNTNUMBER), Mage::getStoreConfig(self::XML_PATH_MODULE_ENCRYPTIONKEY), (bool)Mage::getStoreConfig(self::XML_PATH_MODULE_DEBUG));

        $params = array(
            'accountNumber' => '',
            'countryCode' => 'SE', // Supported only "SE"
            'socialSecurityNumber' => $ssn
        );
        $result = $px->GetConsumerLegalAddress($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            if (preg_match('/\bInvalid parameter:SocialSecurityNumber\b/i', $result['description'])) {
                $data = array(
                    'success' => false,
                    'message' => Mage::helper('aait_ssn')->__('Invalid Social Security Number')
                );
                $this->getResponse()->setHeader('Content-type', 'application/json');
                $this->getResponse()->setBody(Zend_Json::encode($data));
                return;
            }

            $data = array(
                'success' => false,
                'message' => $result['errorCode'] . '(' . $result['description'] . ')'
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        $data = array(
            'success' => true,
            'first_name' => $result['firstName'],
            'last_name' => $result['lastName'],
            'address_1' => $result['address1'],
            'address_2' => $result['address2'],
            'postcode' => $result['postNumber'],
            'city' => $result['city'],
            'country' => $result['country']
        );

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Zend_Json::encode($data));
    }

}
