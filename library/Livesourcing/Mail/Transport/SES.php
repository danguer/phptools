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


/**
 * @see Zend_Mail_Transport_Abstract
 */
require_once 'Zend/Mail/Transport/Abstract.php';


/**
 * Transport for amazon Simple Email Service (SES)
 * Inspired by Zend_Mail_Transport_Sendmail
 */
class Livesourcing_Mail_Transport_SES extends Zend_Mail_Transport_Abstract
{
    /**
     * Subject
     * @var string
     * @access public
     */
    public $subject = null;


    /**
     * Config options for sendmail parameters
     *
     * @var string
     */
    public $parameters;

    /**
     * EOL character string
     * @var string
     * @access public
     */
    public $EOL = "\r\n";

    /**
     * Constructor.
     *
     * @param  string|array|Zend_Config $parameters Must have at least aws_key and aws_secret
     * @return void
     */
    public function __construct($parameters = array())
    {
        if ($parameters instanceof Zend_Config) { 
            $parameters = $parameters->toArray(); 
        }
        
        if (!is_array($parameters)) {
        	throw new Zend_Exception('Parameters must be an array');
        }
        
        $this->parameters = $parameters;
        
        if (!isset($this->parameters['aws_key']) || empty($this->parameters['aws_key'])) {
        	throw new Zend_Exception('You must send the aws_key (access key) param');
        }
        if (!isset($this->parameters['aws_secret']) || empty($this->parameters['aws_secret'])) {
        	throw new Zend_Exception('You must send the aws_secret (aws secret) param');
        }
        
    }


    /**
     * Send mail using Amazon SES
     *
     * @access public
     * @return void
     * @throws Zend_Mail_Transport_Exception if parameters is set
     *         but not a string
     * @throws Zend_Mail_Transport_Exception on mail() failure
     */
    public function _sendMail()
    {
    	$region = null;
    	if (isset($this->parameters['aws_region']))
    		$region = $this->parameters['aws_region'];
    		
        $ses = new Livesourcing_Service_Amazon_SES($this->parameters['aws_key'], $this->parameters['aws_secret'], $region);
   		//build 
   		
   		$email_body = $this->header.$this->EOL;
   		//add to destinations
   		$email_body .= "To: {$this->recipients}{$this->EOL}";
   		$email_body .= "Subject: ".$this->_mail->getSubject().$this->EOL;
   		$email_body .= $this->EOL;
   		$email_body .= $this->body;
   		
   		//print $email_body; exit();
   		$ses->sendRawEmail($email_body);
    }


    /**
     * Format and fix headers
     *
     * mail() uses its $to and $subject arguments to set the To: and Subject:
     * headers, respectively. This method strips those out as a sanity check to
     * prevent duplicate header entries.
     *
     * @access  protected
     * @param   array $headers
     * @return  void
     * @throws  Zend_Mail_Transport_Exception
     */
    protected function _prepareHeaders($headers)
    {
        if (!$this->_mail) {
            /**
             * @see Zend_Mail_Transport_Exception
             */
            require_once 'Zend/Mail/Transport/Exception.php';
            throw new Zend_Mail_Transport_Exception('_prepareHeaders requires a registered Zend_Mail object');
        }

        // mail() uses its $to parameter to set the To: header, and the $subject
        // parameter to set the Subject: header. We need to strip them out.
        if (0 === strpos(PHP_OS, 'WIN')) {
            // If the current recipients list is empty, throw an error
            if (empty($this->recipients)) {
                /**
                 * @see Zend_Mail_Transport_Exception
                 */
                require_once 'Zend/Mail/Transport/Exception.php';
                throw new Zend_Mail_Transport_Exception('Missing To addresses');
            }
        } else {
            // All others, simply grab the recipients and unset the To: header
            if (!isset($headers['To'])) {
                /**
                 * @see Zend_Mail_Transport_Exception
                 */
                require_once 'Zend/Mail/Transport/Exception.php';
                throw new Zend_Mail_Transport_Exception('Missing To header');
            }

            unset($headers['To']['append']);
            $this->recipients = implode(',', $headers['To']);
        }

        // Remove recipient header
        unset($headers['To']);

        // Remove subject header, if present
        if (isset($headers['Subject'])) {
            unset($headers['Subject']);
        }

        // Prepare headers
        parent::_prepareHeaders($headers);

        // Fix issue with empty blank line ontop when using Sendmail Trnasport
        $this->header = rtrim($this->header);
    }

}
