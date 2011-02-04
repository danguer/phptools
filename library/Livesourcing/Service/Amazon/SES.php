<?php
/*
Copyright 2011 Daniel Guerrero, LiveSourcing. 
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice, this list of
      conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice, this list
      of conditions and the following disclaimer in the documentation and/or other materials
      provided with the distribution.

   3. Neither the name of LiveSourcing, Inc. nor the names of its
      contributors may be used to endorse or promote products derived from this
      software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY LiveSourcing "AS IS" AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those of the
authors and should not be interpreted as representing official policies, either expressed
or implied, of LiveSourcing.
 */

require_once 'Zend/Service/Amazon/Abstract.php';
require_once 'Livesourcing/Service/Amazon/SES/Response.php';
require_once 'Zend/Service/Amazon/Exception.php';

/**
 * Class for using Amazon SES, was inspired by ec2 abstract class
 *
 */

class Livesourcing_Service_Amazon_SES extends Zend_Service_Amazon_Abstract {
    /**
     * The HTTP query server
     */
    protected $_sesEndpoint = 'email.{%region%}.amazonaws.com';
    
    /**
     * The API version to use
     */
    protected $_sesApiVersion = '2010-12-01';

    /**
     * XML Namespace
     */
    protected $_xmlNamespace = 'http://email.amazonaws.com/doc/2010-12-01/';
    
    /**
     * Period after which HTTP request will timeout in seconds
     */
    protected $_httpTimeout = 10;

    /**
     * @var string Amazon Region
     */
    protected $_region;

    /**
     * An array that contains all the valid Amazon SES Regions.
     *
     * @var array
     */
    protected static $_validSesRegions = array('eu-west-1', 'us-east-1');    
    
    /**
     * Create Simple Email Service client.
     *
     * @param  string $access_key       Override the default Access Key
     * @param  string $secret_key       Override the default Secret Key
     * @param  string $region           Sets the AWS Region
     * @return void
     */
    public function __construct($accessKey=null, $secretKey=null, $region=null)
    {
        if(!$region) {
			$region = 'us-east-1';
        } else {
            // make rue the region is valid
            if(!empty($region) && !in_array(strtolower($region), self::$_validSesRegions, true)) {
                throw new Zend_Service_Amazon_Exception('Invalid Amazon SES Region');
            }
        }

        $this->_region = $region;

        parent::__construct($accessKey, $secretKey);
    }    
    
    /**
     * Method for making easy the build of members in requests
     *
     * @param array $params
     * @param string $key prefix of the members
     * @param array $items
     */
    protected function buildParamMembers(&$params, $key, $items) {
    	foreach($items as $k => $s) {
    		if (is_array($s)) {
    			foreach($s as $k1 => $s1) {
	    			$params[$key . '.member.' . ($k+1) . '.' . ($k1)] = $s1;
    			}
    		} else
    			$params[$key . '.member.' . ($k+1)] = $s;
    	}
    }    
    
    /**
     * Method to fetch the AWS Region
     *
     * @return string
     */
    protected function _getEndPoint()
    {
        return str_replace("{%region%}", $this->_region, $this->_sesEndpoint);
    }    
    
    /**
     * Sends a HTTP request to the queue service using Zend_Http_Client
     *
     * @param array $params         List of parameters to send with the request
     * @return Livesourcing_Service_Amazon_SES_Response
     * @throws Zend_Service_Amazon_Exception
     */
    protected function sendRequest(array $params = array())
    {
    	require_once 'Zend/Crypt/Hmac.php';
        $url = 'https://' . $this->_getEndPoint() . '/';
        
        //new way to sign
        $date = gmdate('D, j M Y H:i:s \G\M\T');
        
        $hmac = Zend_Crypt_Hmac::compute($this->_getSecretKey(), 'SHA256', $date, Zend_Crypt_Hmac::BINARY);
        $hmac_signature = base64_encode($hmac);
                
        try {
            /* @var $request Zend_Http_Client */
            $request = self::getHttpClient();
            $request->resetParameters();

            $request->setConfig(array(
                'timeout' => $this->_httpTimeout
            ));

            $request->setUri($url);
            $request->setMethod(Zend_Http_Client::POST);
            
            $aws_key = $this->_getAccessKey();
            $signature = "AWS3-HTTPS AWSAccessKeyId={$aws_key},Algorithm=HMACSHA256,Signature={$hmac_signature}";
            $request->setHeaders('X-Amzn-Authorization', $signature);
            $request->setHeaders('Date', $date);
            $request->setParameterPost($params);

            $httpResponse = $request->request();


        } catch (Zend_Http_Client_Exception $zhce) {
            $message = 'Error in request to AWS service: ' . $zhce->getMessage();
            throw new Zend_Service_Amazon_Exception($message, $zhce->getCode(), $zhce);
        }
        $response = new Livesourcing_Service_Amazon_SES_Response($httpResponse);
        $this->checkForErrors($response);

        return $response;
    }
    
    /**
     * Checks for errors responses from Amazon
     *
     * @param Livesourcing_Service_Amazon_SES_Response $response the response object to
     *                                                   check.
     *
     * @return void
     *
     * @throws Zend_Service_Amazon_Exception if one or more errors are
     *         returned from Amazon.
     */
    protected function checkForErrors(Livesourcing_Service_Amazon_SES_Response $response)
    {
    	if ($response->getDocument()->getElementsByTagName('Error')->length > 0) {
    		$sxml = simplexml_import_dom($response->getDocument());
    		$code    = (string)$sxml->Error->Code;
            $message = (string)$sxml->Error->Message;
            throw new Zend_Service_Amazon_Exception($message, $code);
    	}    	
    }    
    
	public function deleteVerifiedEmailAddress($email) {
        $params = array();
        $params['Action'] = 'DeleteVerifiedEmailAddress';
        $params['EmailAddress'] = $email;
        
        $this->sendRequest($params);
	}    
    
	public function getSendQuota() {
        $params = array();
        $params['Action'] = 'GetSendQuota';
        
        $response = $this->sendRequest($params);
        $response->setNamespace($this->_xmlNamespace);
        $sxml = simplexml_import_dom($response->getDocument());
        
		return array(
			'SentLast24Hours' => (string)$sxml->GetSendQuotaResult->SentLast24Hours,
			'Max24HourSend' => (string)$sxml->GetSendQuotaResult->Max24HourSend,
			'MaxSendRate' => (string)$sxml->GetSendQuotaResult->Max24HourSend,
		);			
	}
	
	public function getSendStatistics() {
        $params = array();
        $params['Action'] = 'GetSendStatistics';
        
        $response = $this->sendRequest($params);
        $response->setNamespace($this->_xmlNamespace);
        $sxml = simplexml_import_dom($response->getDocument());
        
		return array(
			'DeliveryAttempts' => (string)$sxml->GetSendStatisticsResult->SendDataPoints->member->DeliveryAttempts,
			'Timestamp' => (string)$sxml->GetSendStatisticsResult->SendDataPoints->member->Timestamp,
			'Rejects' => (string)$sxml->GetSendStatisticsResult->SendDataPoints->member->Rejects,
			'Bounces' => (string)$sxml->GetSendStatisticsResult->SendDataPoints->member->Bounces,
			'Complaints' => (string)$sxml->GetSendStatisticsResult->SendDataPoints->member->Complaints,
		);			
	}
	
	public function sendEmail($destination, $subject, $message, $source, $options=array()) {
        $params = array();
        $params['Action'] = 'SendEmail';
        $params['Message.Body.Text.Data'] = $message;
        $params['Source'] = $source;
        
        $options = array_merge(array(
        	'subject_charset' => 'UTF-8',
        	'message_text_charset' => 'UTF-8',
        	'message_html_charset' => 'UTF-8',
        	'message_html' => null,
        
        	'reply' => array(), 
        	'return_path' => null, 
        	'bcc' => array(),
        	'cc' => array(),
        ), $options);
        
        $params['Message.Subject.Charset'] = $options['subject_charset'];
        $params['Message.Subject.Data'] = $subject;
        
        $params['Message.Body.Text.Charset'] = $options['message_text_charset'];
        
        if (!empty($options['message_html'])) {
        	$params['Message.Body.Html.Charset'] = $options['message_html_charset'];
        	$params['Message.Body.Html.Data'] = $options['message_html'];
        }
        
        if (!is_array($destination)) 
        	$destination = array($destination);

        //build main destination
        $this->buildParamMembers($params, 'Destination.ToAddresses', $destination);
        
        if (count($options['reply']))
        	$this->buildParamMembers($params, 'ReplyToAddresses', $options['reply']);

        if (count($options['cc']))
        	$this->buildParamMembers($params, 'Destination.CcAddresses', $options['cc']);
        	
        if (count($options['bcc']))
        	$this->buildParamMembers($params, 'Destination.BccAddresses', $options['bcc']);
        
        if ($options['return_path']) 
        	$params['ReturnPath'] = $options['return_path'];
        	
        $response = $this->sendRequest($params);
        $response->setNamespace($this->_xmlNamespace);
        $sxml = simplexml_import_dom($response->getDocument());
        return (string)$sxml->SendEmailResult->MessageId;
	}
	
	public function sendRawEmail($raw, $destination=null, $source=null) {
        $params = array();
        $params['Action'] = 'SendRawEmail';
        $params['RawMessage.Data'] = base64_encode($raw);
        
        if ($source)
        	$params['Source'] = $source;
        	
        if ($destination) {
        	if (!is_array($destination)) 
        		$destination = array($destination);

	        $this->buildParamMembers($params, 'Destinations', $destination);
        }
         
        $response = $this->sendRequest($params);
        $response->setNamespace($this->_xmlNamespace);
        $sxml = simplexml_import_dom($response->getDocument());
        return (string)$sxml->SendEmailResult->MessageId;
	}
	
	public function listVerifiedEmailAddresses() {
        $params = array();
        $params['Action'] = 'ListVerifiedEmailAddresses';
        
        $response = $this->sendRequest($params);
        $response->setNamespace($this->_xmlNamespace);

        $return = array();
        $sxml = simplexml_import_dom($response->getDocument());
        foreach ($sxml->ListVerifiedEmailAddressesResult->VerifiedEmailAddresses->member as $email) {
        	$return[] = (string)$email;
        }
        
        return $return;
	}
	
	public function verifyEmailAddress($email) {
        $params = array();
        $params['Action'] = 'VerifyEmailAddress';
        $params['EmailAddress'] = $email;
        
        $this->sendRequest($params);
	}
}
