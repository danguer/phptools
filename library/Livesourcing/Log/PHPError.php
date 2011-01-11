<?php
/*
Copyright 2010 Daniel Guerrero, LiveSourcing. 
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
 * Log instance that catches php errors or exceptions not handled
 * also it provides an easy method for creating an email writer to 
 * grab the messages and send to several emails
 */

require_once 'Zend/Log.php';

class Livesourcing_Log_PHPError extends Zend_Log {
	protected $error_map = array(
		E_ERROR => self::ERR,
		E_WARNING => self::WARN,
		E_PARSE => self::CRIT,
		E_NOTICE => self::NOTICE,
		E_CORE_ERROR => self::ERR,
		E_CORE_WARNING => self::WARN,
		E_COMPILE_ERROR => self::ERR,
		E_COMPILE_WARNING => self::WARN,
		E_USER_ERROR => self::ERR,
		E_USER_WARNING => self::WARN,
		E_USER_NOTICE => self::NOTICE,
		//E_STRICT => self::DEBUG, //by default disabled, you can enable through addErrorMap(E_STRICT, Zend_Log::DEBUG, 'E_STRICT');
	);
	
	protected $error_map_names = array(
		E_ERROR => 'E_ERROR',
		E_WARNING => 'E_WARNING',
		E_PARSE => 'E_PARSE',
		E_NOTICE => 'E_NOTICE',
		E_CORE_ERROR => 'E_CORE_ERROR',
		E_CORE_WARNING => 'E_CORE_WARNING',
		E_COMPILE_ERROR => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING => 'E_COMPILE_WARNING',
		E_USER_ERROR => 'E_USER_ERROR',
		E_USER_WARNING => 'E_USER_WARNING',
		E_USER_NOTICE => 'E_USER_NOTICE',
		E_STRICT => 'E_STRICT',
	);
	
	public function __construct(Zend_Log_Writer_Abstract $writer = null) {
		//add possible maps according version
		if (defined('E_RECOVERABLE_ERROR')) $this->addErrorMap(E_RECOVERABLE_ERROR, self::ERR, 'E_RECOVERABLE_ERROR');
		if (defined('E_DEPRECATED')) $this->addErrorMap(E_RECOVERABLE_ERROR, self::WARN, 'E_RECOVERABLE_ERROR');
		if (defined('E_USER_DEPRECATED')) $this->addErrorMap(E_RECOVERABLE_ERROR, self::WARN, 'E_RECOVERABLE_ERROR');
		
		parent::__construct($writer);
		set_error_handler(array($this, 'errorHandler'));
		register_shutdown_function(array($this, 'shutdownHandler'));
	}
	
	public function addErrorMap($errcode, $priority, $name=null) {
		$this->error_map[$errcode] = $priority;
		
		if ($name)
			$this->error_map_names[$errcode] = $name;	
	}
	
	public function removeErrorMap($errcode) {
		unset($this->error_map[$errcode]);
		unset($this->error_map_names[$errcode]);
	}
	
	public function errorHandler($errno, $errstr, $errfile, $errline) {
		if (isset($this->error_map[$errno])) {
			$priority = $this->error_map[$errno];
			$error_name = $errno;
			if (isset($this->error_map_names[$errno])) 
				$error_name = $this->error_map_names[$errno];
			
			$message = "[{$errfile}:{$errline}] {$error_name}, {$errstr}";
			$this->log($message, $priority);
		}
	}
	
	public function shutdownHandler() {
		$last_error = error_get_last();
		if ($last_error) {
			$errno = $last_error['type'];
			
			if ($errno == E_ERROR 
				|| $errno == E_PARSE
				|| $errno == E_CORE_ERROR
				|| $errno == E_COMPILE_ERROR) {
				$errstr = $last_error['message'];
				$errfile = $last_error['file'];
				$errline = $last_error['line'];
				
				$this->errorHandler($errno, $errstr, $errfile, $errline);
				
				//force as won't process normally
				self::__destruct();
			}
		}
	}
	
	/**
	 * Util function for creating an instance for sending errors to email,
	 * useful for logging CLI or web applications in a fast way
	 *
	 * @param array|string $recipients array of recipients, or a single email
	 * @param string $subject subject to be prepend in the email
	 * @return Livesourcing_Log_PHPError
	 */
	public static function createMail($recipients, $subject='PHP Script Errors') {
		require_once 'Zend/Mail.php';
		require_once 'Zend/Log/Writer/Mail.php';
		$mail = new Zend_Mail('UTF-8');
		
		if (!is_array($recipients))
			$recipients = array($recipients);
		
		foreach($recipients as $email) {
			$mail->addTo($email);
		}
		$writer = new Zend_Log_Writer_Mail($mail);
		
		if ($subject)
			$writer->setSubjectPrependText($subject);
			
		return new self($writer);
	}
}
