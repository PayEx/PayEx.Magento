<?php

class PayEx_Payments_GetaddrController extends Mage_Core_Controller_Front_Action
{
    const XML_PATH_MODULE_DEBUG = 'payment/payex_financing/checkout_field';
    const XML_PATH_MODULE_ACCOUNTNUMBER = 'payment/payex_financing/accountnumber';
    const XML_PATH_MODULE_ENCRYPTIONKEY = 'payment/payex_financing/encryptionkey';

    public function indexAction()
    {
        // Get initial data from request
        $ssn = trim($this->getRequest()->getParam('ssn'));
        if (empty($ssn)) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('payex')->__('Social security number is empty')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        //$ssn = preg_replace('/[^0-9]/s', '', $ssn);

        // Get Country Code
        //$country_code = Mage::helper('payex/tools')->getCountryCodeBySSN($ssn);
        //if (!$country_code) {
        //    $data = array(
        //        'success' => false,
        //        'message' => Mage::helper('payex')->__('Invalid Social Security Number')
        //    );
        //    $this->getResponse()->setHeader('Content-type', 'application/json');
        //    $this->getResponse()->setBody(Zend_Json::encode($data));
        //    return;
        //}

        $country_code = trim($this->getRequest()->getParam('country_code'));
        if (empty($country_code)) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('payex')->__('Country is empty')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        if (!in_array($country_code, array('SE', 'NO'))) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('payex')->__('Your country don\'t supported')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        // strip whitespaces from postcode to pass validation
        $postcode = preg_replace('/\s+/', '', $this->getRequest()->getParam('postcode'));
        if (empty($postcode)) {
            $data = array(
                'success' => false,
                'message' => Mage::helper('payex')->__('Postcode is empty')
            );
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        // Init PayEx
        $px = Mage::helper('payex/api')->getPx();
        $px->setEnvironment(
            Mage::getStoreConfig(self::XML_PATH_MODULE_ACCOUNTNUMBER),
            Mage::getStoreConfig(self::XML_PATH_MODULE_ENCRYPTIONKEY),
            (bool)Mage::getStoreConfig(self::XML_PATH_MODULE_DEBUG)
        );

        // Call PxOrder.GetAddressByPaymentMethod
        $params = array(
            'accountNumber' => '',
            'paymentMethod' => 'PXFINANCINGINVOICE' . $country_code,
            'ssn' => $ssn,
            'zipcode' => $postcode,
            'countryCode' => $country_code,
            'ipAddress' => Mage::helper('core/http')->getRemoteAddr()
        );
        $result = $px->GetAddressByPaymentMethod($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $message = Mage::helper('payex/tools')->getVerboseErrorMessage($result);
            $data = array(
                'success' => false,
                'message' => $message
            );

            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Zend_Json::encode($data));
            return;
        }

        // Parse name field
        $name = Mage::helper('payex/tools')->getNameParser()->parse_name($result['name']);

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

        // Save data in Session
        Mage::getSingleton('checkout/session')->setPayexSSN($ssn);
        Mage::getSingleton('checkout/session')->setPayexSSNData($data);

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Zend_Json::encode($data));
    }

}
