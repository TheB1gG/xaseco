<?php
/**
 * ManiaLib - Lightweight PHP framework for Manialinks
 * 
 * @copyright   Copyright (c) 2009-2011 NADEO (http://www.nadeo.com)
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 * @version     $Revision: 493 $:
 * @author      $Author: maximeraoust $:
 * @date        $Date: 2011-05-05 18:51:29 +0200 (jeu., 05 mai 2011) $:
 */


/**
 * Lightweight REST client for Web Services.
 * 
 * Requires CURL and JSON extensions
 */
class Client
{
	public $lastRequestInfo;
	
	protected $APIURL = 'https://ws.trackmania.com';
	protected $username;
	protected $password;
	protected $contentType;
	protected $acceptType;
	protected $serializeCallback;
	protected $unserializeCallback;
	protected $timeout;
  public $debug;
	
	function __construct($username, $password, $debug = false) 
	{
		if (!function_exists('curl_init')) 
		{
			die('Freezone-Plugin needs the CURL PHP extension.');
		}
		$this->username = $username;
		$this->password = $password;
		$this->contentType = 'application/json';
		$this->acceptType = 'application/json';
		$this->serializeCallback = 'json_encode';
		$this->unserializeCallback = 'json_decode';
		$this->timeout = 3;
    $this->debug = $debug;
	}

	function setAuth($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
	}
	
	function setAPIURL($URL)
	{
		$this->APIURL = $URL;
	}
	
	function setContentType($contentType)
	{
		$this->contentType = $contentType;
	}
	
	function setAcceptType($acceptType)
	{
		$this->acceptType = $acceptType;
	}
	
	function setSerializeCallback($callback)
	{
		$this->serializeCallback = $callback;
	}
	
	function setUnserializeCallback($callback)
	{
		$this->unserializeCallback = $callback;
	}
	
	function setTimeout($timeout)
	{
		$this->timeout = $timeout;
	}
	
	function execute($verb, $ressource, array $params = array())
	{
		$url = $this->APIURL.$ressource;
		if($verb == 'POST' || $verb == 'PUT')
		{
			 $data = array_pop($params);
			 $data = call_user_func($this->serializeCallback, $data);
		}
		else
		{
			$data = null;
		}
		if($params)
		{
			$params = array_map('urlencode', $params);
			array_unshift($params, $url);
			$url = call_user_func_array('sprintf', $params);
		}
		
		$header[] = 'Accept: '.$this->acceptType;
		$header[] = 'Content-type: '.$this->contentType;
		
		$options = array();
		
		switch($verb)
		{
			case 'HEAD':
			case 'GET':
				// Nothing to do
				break;
				
			case 'POST':
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = $data;
				break;
			
			case 'PUT':
				$fh = fopen('php://temp', 'rw');
				fwrite($fh, $data);
				rewind($fh);
				
				$options[CURLOPT_PUT] = true;
				$options[CURLOPT_INFILE] = $fh;
				$options[CURLOPT_INFILESIZE] = strlen($data);
				break;
				
			case 'DELETE':
				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = '';
				$header[] = 'Method: DELETE';
				break;
				
			default:
				throw new FreezoneException('Unsupported HTTP method: '.$verb);
		}
		
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_HTTPHEADER] = $header;
		$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
		$options[CURLOPT_USERPWD] = $this->username.':'.$this->password;
		$options[CURLOPT_TIMEOUT] = $this->timeout;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_USERAGENT] = 'ManiaLib Rest Client'; 
		
		// This normally should not be done
		// But the certificates of our api are self-signed for now
		$options[CURLOPT_SSL_VERIFYHOST] = 0;
		$options[CURLOPT_SSL_VERIFYPEER] = 0;
		
		try 
		{
			$ch = curl_init();
			curl_setopt_array($ch, $options);
			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);
		}
		catch(Exception $e)
		{
			if($ch)
			{
				curl_close($ch);
			}
			throw $e;
		}
		
		$this->lastRequestInfo = $info;
		
		if($response && $this->unserializeCallback)
		{
			$response = call_user_func($this->unserializeCallback, $response);
		}
		if($this->debug) {
      $this->writedebug("[".date('r')."] ".$info['http_code']." ('".$verb."', '".$ressource."', '".(is_array($data) ? print_r($data,1) : $data)."')");
    }
    
		if($info['http_code'] == 200)
		{
			return $response;
		}
		else
		{
			if(is_object($response) && property_exists($response, 'message'))
			{
				$message = $response->message;
			}
			else
			{
				$message = 'API error. Check the HTTP error code.';
			}
			throw new FreezoneException($message, $info['http_code']);
		}
	}
  
  static function writedebug($msg) {
    $fp = fopen("freezone.log","a+");
    fwrite($fp, $msg."\r\n");
    fclose($fp);
  }
}
class FreezoneException extends Exception { 
  
  public function __construct($message, $code = 0) {
    parent::__construct($message, $code);
  }
  
  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }
}
?>
