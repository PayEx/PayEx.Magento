<?php
class AAIT_SocialSecurityNumber_GetaddrController extends Mage_Core_Controller_Front_Action
{
    const XML_PATH_MODULE_DEBUG = 'aait_ssn/aait_ssn/debug';
    const XML_PATH_MODULE_ACCOUNTNUMBER = 'aait_ssn/aait_ssn/accountnumber';
    const XML_PATH_MODULE_ENCRYPTIONKEY = 'aait_ssn/aait_ssn/encryptionkey';

    public function indexAction()
    {
        // Get initial data from request
        $ssn = $this->getRequest()->getParam('ssn');
        if (empty($ssn)) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('aait_ssn')->__('Social security number is empty')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        $ssn = preg_replace('/[^0-9]/s', '', $ssn);

        // Get Country Code
        $country_code = Mage::helper('aait_ssn')->getCountryCodeBySSN($ssn);
        if (!$country_code) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('aait_ssn')->__('Invalid Social Security Number')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        if (!in_array($country_code, array('SE', 'NO'))) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('aait_ssn')->__('Your country don\'t supported')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        // Init PayEx
        $px = Mage::helper('aait_ssn')->getPx();
        $px->setEnvironment(Mage::getStoreConfig(self::XML_PATH_MODULE_ACCOUNTNUMBER), Mage::getStoreConfig(self::XML_PATH_MODULE_ENCRYPTIONKEY), (bool)Mage::getStoreConfig(self::XML_PATH_MODULE_DEBUG));

        // Call PxOrder.GetAddressByPaymentMethod
        $params = array(
            'accountNumber' => '',
            'paymentMethod' => 'PXFINANCINGINVOICE' . $country_code,
            'ssn' => $ssn,
            'zipcode' => '',
            'countryCode' => $country_code,
            'ipAddress' => Mage::helper('core/http')->getRemoteAddr()
        );
        $result = $px->GetAddressByPaymentMethod($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $data = array(
                'success' => false,
                'message' => $result['errorCode'] . '(' . $result['description'] . ')'
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        // Parse name field
        $name = Mage::helper('aait_ssn')->getNameParser()->parse_name($result['name']);

        $data = array(
            'success' => true,
            'first_name' => $name['fname'],
            'last_name' => $name['lname'],
            'address_1' => $result['streetAddress'],
            'address_2' => !empty($result['coAddress']) ? 'c/o ' . $result['coAddress'] : '',
            'postcode' => $result['zipCode'],
            'city' => $result['city'],
            'country' => $result['countryCode']
        );

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Zend_Json::encode($data));
    }

}
