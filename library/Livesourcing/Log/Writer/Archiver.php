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
 * Log Writer to save files through date mask or archiver (rename of previous logs 
 * after reach X size)
 */

require_once 'Zend/Log/Writer/Stream.php';
require_once 'Zend/Log/Exception.php';

class Livesourcing_Log_Writer_Archiver extends Zend_Log_Writer_Stream  {
	const FLAG_DATE = 0x1;
	const FLAG_SIZE = 0x2;
	private $params = null;
	private $current_size = -1;
	private $filename = null;
	
	/**
	 * Build the log writer, allowed params:
	 * filepath => base dir for storing logs
	 * prefix => prefix to be used, mandatory for FLAG_SIZE
	 * suffix => suffix to add to the filename, default '.log'
	 * flags => choose to use FLAG_DATE, FLAG_SIZE or both (FLAG_DATE | FLAG_SIZE)
	 * date_mask => mask for used in date flag, must be complaint with date() function params
	 * max_size => needed for FLAG_SIZE to check the maximum file size at startup
	 *
	 * @param array $params
	 */
	public function __construct($params) {
		if (!is_array($params))
			$params = array();
		
		$params = $params + array(
			'filepath' => null,
			'prefix' => null,
			'suffix' => '.log',
			'flags' => self::FLAG_DATE,
			'date_mask' => 'Y-m-d',
			'max_size' => 10485760, //in bytes, current 10MB			
		);
		$params['mode'] = 'a'; //always append
		
		$this->params = $params;
		if ($params['filepath'] == null || !is_dir($params['filepath'])) {
			throw new Zend_Log_Exception('Must provide the filepath dir');
		}		
		
		$filename = "{$params['prefix']}";
		if ($params['flags'] & self::FLAG_DATE) {
			$filename .= date($params['date_mask']);
		}
		
		if ($params['flags'] & self::FLAG_SIZE) {			
			if (empty($filename)) {
				throw new Zend_Log_Exception('Must provide the prefix');
			}
			
			$this->filename = $filename;
			$this->checkSize();			
		}
		
		$params['stream'] = $this->params['filepath'] . DIRECTORY_SEPARATOR . $filename . $this->params['suffix'];
		parent::__construct($params['stream'], $params['mode']);
	}
	
	/**
     * Write a message to the log.
     * Check the size if have reached the max_size
     *
     * @param  array  $event  event data
     * @return void
     */
    protected function _write($event) {        
    	//try to write through parent
        parent::_write($event);
        
        //calculate new length
        $line = $this->_formatter->format($event);
        $this->current_size += mb_strlen($line);
        
        $this->checkSize();
    }
	
	/**
	 * Check the size of the file	 
	 *
	 */
	protected function checkSize() {
		//check if we need to move current filename
		$filepath = $this->params['filepath'] . DIRECTORY_SEPARATOR . $this->filename . $this->params['suffix'];
		if ($this->current_size == -1) {
			if (file_exists($filepath))
				$this->current_size = filesize($filepath);
			else
				$this->current_size = 0; //new file
		}
		
		if (file_exists($filepath) &&  $this->current_size >= $this->params['max_size']) {
			$reopen = false;
			if (is_resource($this->_stream)) {
				fclose($this->_stream);
				$reopen = true;
			}
			
			//do a rename
			$index = 1;
			do {
				$new_filename = $this->filename . ".{$index}";
				$new_filepath = $this->params['filepath'] . DIRECTORY_SEPARATOR . $new_filename . $this->params['suffix'];
				$index++;
				
				if (!file_exists($new_filepath)) {
					//copy current stream to filepath
					rename($filepath, $new_filepath);
					break;
				}
				
			} while (true);
			
			//reopen if needed
			if ($reopen) {
				//taken from parent code
				if (! $this->_stream = @fopen($filepath, $this->params['mode'], false) ) {
	                $msg = "\{$filepath}\" cannot be opened with mode \"{$this->params['mode']}\"";
	                throw new Zend_Log_Exception($msg);
	            }
			}	
		}
	}
}