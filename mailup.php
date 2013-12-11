<?php

/** Manages communication with MailUp */
class MailUp {
	/** Import process status: not started.
	* @var int
	*/
	const IMPORTPROCESS_NOTSTARTED = 1;
	/** Import process status: running.
	* @var int
	*/
	const IMPORTPROCESS_RUNNING = 2;
	/** Import process status: completed.
	* @var int
	*/
	const IMPORTPROCESS_COMPLETED = 3;
	/** Import process status: failed.
	* @var int
	*/
	const IMPORTPROCESS_FAILED = 4;
	/** Import occur only on email channel (mobile number, if present, is discarded)
	* @var int
	*/
	const IMPORTTYPE_EMAIL = 1;
	/** Subscriptions occur only on SMS channel (email address, if present, is discarded)
	* @var int
	*/
	const IMPORTTYPE_SMS = 2;
	/** Subscriptions occur on both email and SMS channels (no field is discarded)
	* @var int
	*/
	const IMPORTTYPE_SMSEMAIL = 3;
	/** Entire number in a single field.
	* @var int
	*/
	const MOBILEIMPORTTYPE_MERGED = 1;
	/** Prefix and Number separated into different fields.
	* @var int
	*/
	const MOBILEIMPORTTYPE_PREFIX_NUMBER = 2;
	/** Soap client identifier: MailUpImport
	* @var string
	*/
	const SOAPCLIENT_IMPORT = 'MailUpImport';
	/** Soap client identifier: MailUpSend
	* @var string
	*/
	const SOAPCLIENT_SEND = 'MailUpSend';
	/** Soap client identifier: MailUpManage
	* @var string
	*/
	const SOAPCLIENT_MANAGE = 'MailUpManage';
	/** Soap client identifier: MailUpReport
	* @var string
	*/
	const SOAPCLIENT_REPORT = 'MailUpReport';
	/** REST endpoint: console
	* @var string
	*/
	const RESTENDPOINT_CONSOLE = 'ConsoleService';
	/** REST endpoint: statistics
	* @var string
	*/
	const RESTENDPOINT_STATISTICS = 'MailStatisticsService';
	/** Duration (in minutes) of the SOAP access key.
	* @var int
	*/
	const SOAPACCESSKEY_DURATION = 60;
	/** Does the SOAP access key expiration is being reset on use?
	* @var bool
	*/
	const SOAPACCESSKEY_RESET_DURATION_ON_USE = false;
	/** Duration (in minutes) of the REST access key.
	* @var int
	*/
	const RESTACCESSKEY_DURATION = 14;
	/** Does the REST access key expiration is being reset on use?
	* @var bool
	*/
	const RESTACCESSKEY_RESET_DURATION_ON_USE = false;
	/** MailUp username.
	* @var string
	*/
	private $username;
	/** MailUp password.
	* @var string
	*/
	private $password;
	/** MailUp REST client ID.
	* @var string
	*/
	private $restClientId;
	/** MailUp REST client secret.
	* @var string
	*/
	private $restClientSecret;
	/** MailUp console url.
	* @var string
	*/
	private $consoleUrl;
	/** MailUp console ID.
	* @var string
	*/
	private $consoleId;
	/** A folder where we can cache some data files.
	* @var string
	*/
	private $cacheFolder;
	/** Debug enabled?
	* @var bool
	*/
	public $debug;
	/** SoapClient instances.
	* @var SoapClient[]
	*/
	private $soapClients;
	/** Current SOAP access key info.
	* @var null|array
	*/
	private $soapAccessKeyCache;
	/** Current REST access key info.
	* @var null|array
	*/
	private $restAccessKeyCache;
	/** Initializes the instance.
	* @param string $username MailUp password.
	* @param string $password MailUp password.
	* @param string $consoleUrl MailUp console url.
	* @param string $restClientId MailUp REST client ID.
	* @param string $restClientSecret MailUp REST client secret.
	* @param int|null $consoleId [default: null] MailUp console ID (if not specified we'll try to auto-detect it).
	* @param string $cacheFolder [default: ''] An optional folder where we can cache some data files (WARNING: should not be public, it'll contain sensitive data!).
	* @param bool $debug [default: false] Debug enabled?
	* @throws Exception Throws an Exception in case of parameter errros.
	*/
	public function __construct($username, $password, $consoleUrl, $restClientId, $restClientSecret, $consoleId = null, $cacheFolder = '', $debug = false) {
		$this->soapClients = array();
		$this->soapAccessKeyCache = null;
		$this->restAccessKeyCache = null;
		$username = empty($username) ? '' : @strval($username);
		if(!strlen($username)) {
			throw new Exception('Missing username parameter');
		}
		$this->username = $username;
		$password = empty($password) ? '' : @strval($password);
		if(!strlen($password)) {
			throw new Exception('Missing password parameter');
		}
		$this->password = $password;
		$restClientId = empty($restClientId) ? '' : @strval($restClientId);
		if(!strlen($restClientId)) {
			throw new Exception('Missing restClientId parameter');
		}
		$this->restClientId = $restClientId;
		$restClientSecret = empty($restClientSecret) ? '' : @strval($restClientSecret);
		if(!strlen($restClientSecret)) {
			throw new Exception('Missing restClientSecret parameter');
		}
		$this->restClientSecret = $restClientSecret;
		$consoleUrl = empty($consoleUrl) ? '' : @strval($consoleUrl);
		if(!strlen($consoleUrl)) {
			throw new Exception('Missing consoleUrl parameter');
		}
		if(preg_match('%https?://(.*)%i', $consoleUrl, $m)) {
			$consoleUrl = $m[1];
		}
		if(preg_match('%^([^/]*)/%', $consoleUrl, $m)) {
			$consoleUrl = $m[1];
		}
		if(!strlen($consoleUrl)) {
			throw new Exception('Invalid consoleUrl parameter');
		}
		$this->consoleUrl = $consoleUrl;
		if(empty($consoleId)) {
			if(@preg_match('/^a(\d+)$/', $this->username, $m)) {
				$consoleId = intval($m[1]);
			}
			else {
				$consoleId = 0;
			}
			if($consoleId <= 0) {
				throw new Exception('Unable to determine consoleId');
			}
		}
		else {
			if((!is_numeric($consoleId)) || (($consoleId = @intval($consoleId)) <= 0)) {
				throw new Exception('Invalid consoleId parameter');
			}
		}
		$this->consoleId = $consoleId;
		$cacheFolder = empty($cacheFolder) ? '' : @strval($cacheFolder);
		if(strlen($cacheFolder)) {
			$cacheFolder = @realpath($cacheFolder);
			if(($cacheFolder === false) || (!is_dir($cacheFolder))) {
				$cacheFolder = '';
			}
			else {
				$cacheFolder = rtrim(str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $cacheFolder), DIRECTORY_SEPARATOR);
			}
		}
		$this->cacheFolder = $cacheFolder;
		$this->debug = $debug ? true : false;
	}
	/** Retrieves a soap client instance.
	* @param string $client One of the MailUp::SOAPCLIENT_ constants.
	* @return SoapClient
	* @throws Exception Throws an Exception in case of errors.
	*/
	private function getSoapClient($client) {
		if(!array_key_exists($client, $this->soapClients)) {
			$wsdlFile = dirname(__FILE__) . '/wsdl/' . preg_replace('/[^\w]/', '', $client) . '.wsdl';
			if(!is_file($wsdlFile)) {
				throw new Exception(sprintf('Unable to find the file %s', $wsdlFile));
			}
			$wsdlPlaceholders = array();
			switch($client) {
				case self::SOAPCLIENT_IMPORT:
					$wsdlPlaceholders['[[MAILUP_CONSOLE_URL]]'] = $this->consoleUrl;
					break;
			}
			$tempFile = '';
			try {
				if(count($wsdlPlaceholders)) {
					$wsdlContents = @file_get_contents($wsdlFile);
					if($wsdlContents === false) {
						throw new Exception(sprintf('Unable to read the content of the file %s', $wsdlFile));
					}
					$wsdlContents = str_replace(array_keys($wsdlPlaceholders), array_values($wsdlPlaceholders), $wsdlContents);
					$s = @tempnam(@sys_get_temp_dir(), 'MailUp');
					if($s === false) {
						throw new Exception('Unable to create a temporary file');
					}
					$tempFile = $s;
					if(!@file_put_contents($tempFile, $wsdlContents)) {
						throw new Exception(sprintf('Unable to write to temporary file %s', $tempFile));
					}
					$wsdlFile = $tempFile;
				}
				$this->soapClients[$client] = new SoapClient(
					$wsdlFile,
					array(
						'exceptions' => true, // Soap errors throw exceptions of type SoapFault
						'trace' => $this->debug ? true : false, // Enables tracing of request so faults can be backtraced
						'cache_wsdl' => $this->debug ? WSDL_CACHE_NONE : WSDL_CACHE_BOTH // Wsdl cache
					)
				);
				if(strlen($tempFile)) {
					@unlink($tempFile);
					$tempFile = '';
				}
			}
			catch(Exception $x) {
				if(strlen($tempFile)) {
					@unlink($tempFile);
				}
				throw $x;
			}
		}
		return $this->soapClients[$client];
	}
	/** Executes a soap call.
	* @param string $client One of the MailUp::SOAPCLIENT_ constants.
	* @param string $method The method to call
	* @param array $args The method arguments (named array).
	* @param null|SoapHeader|SoapHeader[] $headers The headers to be set for the soap call
	* @return SimpleXMLElement
	* @throws Exception Throws an Exception in case of errros.
	*/
	private function execSoap($client, $method, $args, $headers = null, &$returnCode = null) {
		$returnCode = null;
		$sc = $this->getSoapClient($client);
		try {
			$sc->__setSoapHeaders(null);
			if($headers) {
				$sc->__setSoapHeaders($headers);
			}
			$rawResponse = $sc->$method($args);
			$responseField = $method . 'Result';
			$textResponse = $rawResponse->$responseField;
			$xmlResponse = simplexml_load_string($textResponse, 'SimpleXMLElement', LIBXML_NOCDATA);
			switch($client) {
				case self::SOAPCLIENT_SEND:
				case self::SOAPCLIENT_REPORT:
					MailUpSRException::checkSoapResponse($xmlResponse, $this);
					unset($xmlResponse->errorCode, $xmlResponse->errorDescription);
					return $xmlResponse;
				case self::SOAPCLIENT_IMPORT:
					$body = $xmlResponse->mailupBody;
					$returnCode = MailUpImportException::checkSoapResponseBody($body, $this);
					unset($body->ReturnCode);
					return $body;
			}
			return $xmlResponse;
		}
		catch(Exception $x) {
			if($this->debug) {
				throw new Exception($x->getMessage() . $this->getDebugInfo($client), $x->getCode());
			}
			else {
				throw $x;
			}
		}
	}
	private function execRest($endPoint, $path, $verb = 'GET', $dataToSend = '') {
		$tempFile = false;
		try {
			switch($endPoint) {
				case self::RESTENDPOINT_CONSOLE:
				case self::RESTENDPOINT_STATISTICS:
					$url = 'https://services.mailup.com/API/v1.1/Rest/' . $endPoint . '.svc';
					break;
				default:
					throw new Exception("Unknown REST endpoint: $endPoint");
			}
			$url .= '/' . ltrim($path, '/');
			if(!function_exists('curl_init')) {
				throw new Exception('cURL extension not available.');
			}
			$hCurl = @curl_init();
			if($hCurl === false) {
				throw new Exception('curl_init() failed.');
			}
			$errorOption = '';
			if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_URL, $url)) {
				$errorOption = 'CURLOPT_URL';
			}
			if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true)) {
				$errorOption = 'CURLOPT_RETURNTRANSFER';
			}
			if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, false)) {
				$errorOption = 'CURLOPT_SSL_VERIFYPEER';
			}
			if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_SSL_VERIFYHOST, false)) {
				$errorOption = 'CURLOPT_SSL_VERIFYHOST';
			}
			if(!$errorOption) {
				switch(strtoupper($verb)) {
					case 'POST':
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_POST, 1)) {
							$errorOption = 'CURLOPT_POST';
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_POSTFIELDS, $dataToSend)) {
							$errorOption = 'CURLOPT_POSTFIELDS';
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'Content-length: ' . strlen($dataToSend), 'Accept: application/json', 'Authorization: Bearer ' . $this->getRestAccessKey()))) {
							$errorOption = 'CURLOPT_HTTPHEADER';
						}
						break;
					case 'PUT':
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_PUT, 1)) {
							$errorOption = 'CURLOPT_PUT';
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'Content-length: ' . strlen($dataToSend), 'Accept: application/json', 'Authorization: Bearer ' . $this->getRestAccessKey()))) {
							$errorOption = 'CURLOPT_HTTPHEADER';
						}
						if(!$errorOption) {
							$tempFile = @tmpfile();
							if($tempFile === false) {
								throw new Exception('tmpfile() failed');
							}
							if(@fwrite($tempFile, $dataToSend) === false) {
								throw new Exception('fwrite() failed');
							}
							@fseek($tempFile, 0);
							if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_INFILE, $tempFile)) {
								$errorOption = 'CURLOPT_INFILE';
							}
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_INFILESIZE, strlen($dataToSend))) {
							$errorOption = 'CURLOPT_INFILESIZE';
						}
						break;
					case 'DELETE':
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_CUSTOMREQUEST, 'DELETE')) {
							$errorOption = 'DELETE';
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'Content-length: 0', 'Accept: application/json', 'Authorization: Bearer ' . $this->getRestAccessKey()))) {
							$errorOption = 'CURLOPT_HTTPHEADER';
						}
						break;
					default:
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'Content-length: 0', 'Accept: application/json', 'Authorization: Bearer ' . $this->getRestAccessKey()))) {
							$errorOption = 'CURLOPT_HTTPHEADER';
						}
						break;
				}
			}
			if($errorOption) {
				$errorMessage = @curl_error($hCurl);
				if(!is_string($errorMessage)) {
					$errorMessage = '';
				}
				throw new Exception("curl_setopt($errorOption) failed" . (strlen($errorMessage) ? " ($errorMessage)" : '') . '.');
			}
			$result = @curl_exec($hCurl);
			$code = @curl_getinfo($hCurl, CURLINFO_HTTP_CODE);
			@curl_close($hCurl);
			if($tempFile) {
				@fclose($tempFile);
				$tempFile = false;
			}
			if((!$code) || ($code < 200) || ($code > 299)) {
				switch(is_numeric($code) ? $code : 0) {
					case 401:
						throw new Exception('Authorization error');
					case 404:
						throw new Exception('Remote resource not found');
					default:
						throw new Exception("Remote call failed with return code $code");
				}
			}
			$jsResult = @json_decode($result, true);
			if(is_null($jsResult)) {
				throw new Exception("Result from server couldn\'t be parsed");
			}
			return $jsResult;
		}
		catch(Exception $x) {
			if($tempFile) {
				@fclose($tempFile);
				$tempFile = false;
			}
			throw $x;
		}
	}
	/** Returns some debug info associated to the last call made by a soap client.
	* @param string $client One of the MailUp::SOAPCLIENT_ constants.
	* @return string
	*/
	public function getDebugInfo($client) {
		if(!array_key_exists($client, $this->soapClients)) {
			return '';
		}
		$sc = $this->soapClients[$client];
		return "\n\nRequest headers:\n" . $sc->__getLastRequestHeaders() . "\n\nRequest body:\n" . $sc->__getLastRequest() . "\n\nResponse headers:\n" . $sc->__getLastResponseHeaders() . "\n\nResponse body:\n" . $sc->__getLastResponse();
	}
	/** Returns the file name used as cache for the SOAP access key, or an empty string in case it's not creatable.
	* @return string
	*/
	private function getSoapAccessKeyCacheFilename() {
		if(strlen($this->cacheFolder) && is_dir($this->cacheFolder) && is_writable($this->cacheFolder)) {
			return $this->cacheFolder . DIRECTORY_SEPARATOR . 'MailUp.' . preg_replace('/\W/', '', $this->username) . '.accessKey.soap';
		}
		else {
			return '';
		}
	}
	/** Returns the SOAP access key that must be used by some soap call.
	* @param bool $forUse [default: true] Will you use it?
	* @param bool $forUse [default: true] Generate a new access key if we did not already done so?
	* @return string
	* @throws Exception Throws an Exception in case of errros.
	*/
	private function getSoapAccessKey($forUse = true, $generateIfNotSet = true) {
		$timeLimit = time() - self::SOAPACCESSKEY_DURATION * 60 - 30;
		$cacheFile = $this->getSoapAccessKeyCacheFilename();
		if($this->soapAccessKeyCache && ($this->soapAccessKeyCache['timestamp'] <= $timeLimit)) {
			$this->soapAccessKeyCache = null;
		}
		if(!$this->soapAccessKeyCache) {
			$cache = null;
			if(strlen($cacheFile) && is_file($cacheFile)) {
				if((($c = @file_get_contents($cacheFile)) !== false) && (is_array($c = @unserialize($c)))) {
					if(array_key_exists('accessKey', $c) && is_string($c['accessKey']) && strlen($c['accessKey'])) {
						if(array_key_exists('timestamp', $c) && is_int($c['timestamp']) && ($c['timestamp'] > $timeLimit)) {
							$cache = $c;
						}
					}
				}
				if(!$cache) {
					@unlink($cacheFile);
				}
			}
			if(!$cache) {
				if($generateIfNotSet) {
					$response = $this->execSoap(self::SOAPCLIENT_SEND, 'LoginFromId', array('user' => $this->username, 'pwd' => $this->password, 'consoleId' => $this->consoleId));
					$cache = array('accessKey' => (string)$response->accessKey, 'timestamp' => time());
				}
			}
			$this->soapAccessKeyCache = $cache;
		}
		if($this->soapAccessKeyCache) {
			if(self::SOAPACCESSKEY_RESET_DURATION_ON_USE && $forUse) {
				$this->soapAccessKeyCache['timestamp'] = time();
			}
			if(strlen($cacheFile)) {
				if((self::SOAPACCESSKEY_RESET_DURATION_ON_USE && $forUse) || (!is_file($cacheFile))) {
					@file_put_contents($cacheFile, serialize($this->soapAccessKeyCache));
				}
			}
			return $this->soapAccessKeyCache['accessKey'];
		}
		else {
			return '';
		}
	}
	/** Returns the file name used as cache for the REST access key, or an empty string in case it's not creatable.
	* @return string
	*/
	private function getRestAccessKeyCacheFilename() {
		if(strlen($this->cacheFolder) && is_dir($this->cacheFolder) && is_writable($this->cacheFolder)) {
			return $this->cacheFolder . DIRECTORY_SEPARATOR . 'MailUp.' . preg_replace('/\W/', '', $this->username) . '.accessKey.rest';
		}
		else {
			return '';
		}
	}
	/** Returns the REST access key that must be used by some soap call.
	* @param bool $forUse [default: true] Will you use it?
	* @param bool $forUse [default: true] Generate a new access key if we did not already done so?
	* @return string
	* @throws Exception Throws an Exception in case of errros.
	*/
	private function getRestAccessKey($forUse = true, $generateIfNotSet = true) {
		$timeLimit = time() - self::RESTACCESSKEY_DURATION * 60 - 30;
		$cacheFile = $this->getRestAccessKeyCacheFilename();
		if($this->restAccessKeyCache && ($this->restAccessKeyCache['timestamp'] <= $timeLimit)) {
			$this->restAccessKeyCache = null;
		}
		if(!$this->restAccessKeyCache) {
			$cache = null;
			if(strlen($cacheFile) && is_file($cacheFile)) {
				if((($c = @file_get_contents($cacheFile)) !== false) && (is_array($c = @unserialize($c)))) {
					if(array_key_exists('accessKey', $c) && is_string($c['accessKey']) && strlen($c['accessKey'])) {
						if(array_key_exists('timestamp', $c) && is_int($c['timestamp']) && ($c['timestamp'] > $timeLimit)) {
							$cache = $c;
						}
					}
				}
				if(!$cache) {
					@unlink($cacheFile);
				}
			}
			if(!$cache) {
				if($generateIfNotSet) {
					if(!function_exists('curl_init')) {
						throw new Exception('cURL extension not available.');
					}
					$hCurl = @curl_init('https://services.mailup.com/Authorization/OAuth/Token');
					if($hCurl === false) {
						throw new Exception('curl_init() failed.');
					}
					$errorOption = '';
					if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true)) {
						$errorOption = 'CURLOPT_RETURNTRANSFER';
					}
					if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, false)) {
						$errorOption = 'CURLOPT_SSL_VERIFYPEER';
					}
					if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_SSL_VERIFYHOST, false)) {
						$errorOption = 'CURLOPT_SSL_VERIFYHOST';
					}
					if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_POST, 1)) {
						$errorOption = 'CURLOPT_POST';
					}
					if(!$errorOption) {
						$postBody = 'grant_type=password'
							. '&username=' . rawurlencode($this->username)
							. '&password=' . rawurlencode($this->password)
							. '&client_id='. rawurlencode($this->restClientId)
							. '&client_secret=' . rawurlencode($this->restClientSecret)
						;
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_HTTPHEADER, array(
							'Content-length' => strlen($postBody),
							'Accept' => 'application/json',
							'Authorization' => 'Basic ' . base64_encode($this->restClientId . ':' . $this->restClientSecret)
						))) {
							$errorOption = 'CURLOPT_HTTPHEADER';
						}
						if(!$errorOption && !@curl_setopt($hCurl, CURLOPT_POSTFIELDS, $postBody)) {
							$errorOption = 'CURLOPT_POSTFIELDS';
						}
					}
					if($errorOption) {
						$errorMessage = @curl_error($hCurl);
						if(!is_string($errorMessage)) {
							$errorMessage = '';
						}
						throw new Exception("curl_setopt($errorOption) failed" . (strlen($errorMessage) ? " ($errorMessage)" : '') . '.');
					}
					$result = @curl_exec($hCurl);
					$code = @curl_getinfo($hCurl, CURLINFO_HTTP_CODE);
					@curl_close($hCurl);
					if(($code != 200) && ($code != 302)) {
						throw new Exception('Authorization error');
					}
					$result = @json_decode($result);
					if(!(is_object($result) && isset($result->access_token))) {
						throw new Exception('Error in data received');
					}
					$cache = array('accessKey' => (string)$result->access_token, 'timestamp' => time());
				}
			}
			$this->restAccessKeyCache = $cache;
		}
		if($this->restAccessKeyCache) {
			if(self::RESTACCESSKEY_RESET_DURATION_ON_USE && $forUse) {
				$this->restAccessKeyCache['timestamp'] = time();
			}
			if(strlen($cacheFile)) {
				if((self::RESTACCESSKEY_RESET_DURATION_ON_USE && $forUse) || (!is_file($cacheFile))) {
					@file_put_contents($cacheFile, serialize($this->restAccessKeyCache));
				}
			}
			return $this->restAccessKeyCache['accessKey'];
		}
		else {
			return '';
		}
	}
	/** Returns the authorization header that must be used by some SOAP call.
	* @return SoapHeader
	*/
	private function getAuthorizationHeader() {
		$authentication = new stdClass();
		$authentication->User = $this->username;
		$authentication->Password = $this->password;
		$authentication->encType = 'UTF-8';
		return new SoapHeader('http://ws.mailupnet.it/', 'Authentication', $authentication);
	}
	/** Converts an stdClass or a SimpleXMLElement to an associative array.
	* @param stdClass|SimpleXMLElement $stdClass
	* @return array
	*/
	private static function stdClassToArray($stdClass) {
		if(is_object($stdClass)) {
			$stdClass = get_object_vars($stdClass);
		}
		if(is_array($stdClass)) {
			return array_map(array(__CLASS__, __METHOD__), $stdClass);
		}
		else {
			return $stdClass;
		}
	}
	/** Retuns a javascript defining some javascript constants.
	* @return string
	*/
	public static function getDefinesForJavascript() {
		$result = array();
		$result['IMPORTPROCESS'] = array(
			'NOTSTARTED' => self::IMPORTPROCESS_NOTSTARTED,
			'RUNNING' => self::IMPORTPROCESS_RUNNING,
			'COMPLETED' => self::IMPORTPROCESS_COMPLETED,
			'FAILED' => self::IMPORTPROCESS_FAILED
		);
		return json_encode($result);
	}
	/** Converts a SimpleXMLElement to an associative array, with type casting.
	* @param SimpleXMLElement $xml
	* @param array $fields The fields definitions. Keys are the xml field names (starting with '@' if they're attributes), values are their type (string, int, bool, datetime). You can also add '>alias' to rename the field.
	* @return array
	*/
	private static function xmlToArray($xml, $fields) {
		$array = self::stdClassToArray($xml);
		foreach($fields as $fn => $ft) {
			$p = strpos($ft, '>');
			if($p !== false) {
				$alias = substr($ft, $p + 1);
				$ft = substr($ft, 0, $p);
			}
			else {
				$alias = '';
			}
			if($fn[0] === '@') {
				$isAttribute = true;
				$fn = substr($fn, 1);
				if(!array_key_exists('@attributes', $array)) {
					$array['@attributes'] = array();
				}
				if(array_key_exists($fn, $array['@attributes'])) {
					$value = $array['@attributes'][$fn];
					unset($array['@attributes'][$fn]);
				}
				else {
					$value = '';
				}
			}
			else {
				$isAttribute = false;
				if(!array_key_exists($fn, $array)) {
					$array[$fn] = '';
				}
				$value = $array[$fn];
			}
			switch($ft) {
				case 'string':
					if(is_array($value) && (!count($value))) {
						$value = '';
					}
					break;
				case 'int':
					if(is_array($value) && (!count($value))) {
						$value = '';
					}
					if(strlen($value)) {
						if(preg_match('/^\d+$/', $value)) {
							$value = intval($value);
						}
					}
					else {
						$value = null;
					}
					break;
				case 'bool':
					if(is_array($value) && (!count($value))) {
						$value = '';
					}
					if(strlen($value)) {
						switch(strtolower(trim($value))) {
							case 'false':
								$value = false;
								break;
							case 'true':
								$value = true;
								break;
						}
					}
					else {
						$value = null;
					}
					break;
				case 'datetime':
					if(is_array($value) && (!count($value))) {
						$value = '';
					}
					if(strlen($value)) {
						self::unformatTime($value);
					}
					else {
						$value = null;
					}
					break;
			}
			if(strlen($alias)) {
				$array[$alias] = $value;
				unset($array[$fn]);
			}
			else {
				$array[$fn] = $value;
			}
		}
		if(array_key_exists('@attributes', $array) && is_array($array['@attributes']) && (count($array['@attributes']) == 0)) {
			unset($array['@attributes']);
		}
		return $array;
	}
	/** Parses a string representing a date/time. If $time is a valid date/time string, $time it will contain the timestamp.
	* @param string $time
	* @return boolean Returns true if the conversion was successful, false otherwise.
	*/
	private static function unformatTime(&$time) {
		if(preg_match('%(\d+)/(\d+)/(\d+) (\d+)[:.](\d+)[:.](\d+)%', $time, $m)) {
			$defaultTimeZone = date_default_timezone_get();
			date_default_timezone_set('Europe/Rome');
			$time = mktime($m[4], $m[5], $m[6], $m[2], $m[1], $m[3]);
			date_default_timezone_set($defaultTimeZone);
			return true;
		}
		else {
			return false;
		}
	}
	/** Perform a logout (no action taken if not currently logged in).
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function Logout() {
		$accessKey = $this->getSoapAccessKey(false, false);
		if(strlen($accessKey)) {
			$response = $this->execSoap(self::SOAPCLIENT_SEND, 'Logout', array('accessKey' => $accessKey));
			$this->soapAccessKeyCache = null;
			$cacheFile = $this->getSoapAccessKeyCacheFilename();
			if(strlen($cacheFile) && is_file($cacheFile)) {
				@unlink($cacheFile);
			}
		}
	}
	/** Retrieves all the lists.
	* @return array Each array item is an array with the keys<ul>
	*	<li>int <b>id</b> list id.</li>
	*	<li>string <b>name</b> list name.</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function GetLists() {
		$lists = array();
		$response = $this->execSoap(self::SOAPCLIENT_SEND, 'GetLists', array('accessKey' => self::getSoapAccessKey()));
		foreach($response->lists->list as $list) {
			$lists[] = self::xmlToArray($list, array('listID' => 'int>id', 'listName' => 'string>name'));
		}
		return $lists;
	}
	/** Retrieves all the messages (newsletters and sms) associated to a list.
	* @param int $listID The id of the list
	* @return array Each array item is an array with the keys<ul>
	*	<li>int <b>id</b> if of the newsletter or sms.</li>
	*	<li>string <b>subject</b> message subject.</li>
	*	<li>string <b>note</b> notes.</li>
	*	<li>int|null <b>creationDate</b> creation date/time.</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function GetMessages($listID) {
		$messages = array();
		$response = $this->execSoap(self::SOAPCLIENT_SEND, 'GetMessages', array('accessKey' => self::getSoapAccessKey(), 'listID' => $listID));
		foreach($response->list->newsletters->newsletter as $message) {
			$messages[] = self::xmlToArray($message, array('newsletterID' => 'int>id', 'subject' => 'string', 'note' => 'string', 'creationdate' => 'datetime>creationDate'));
		}
		return $messages;
	}
	/** Retrieves all the newsletters associated to a list.
	* @param int $listID The id of the list
	* @return array Each array item is an array with the keys<ul>
	*	<li>int <b>id</b> if of the newsletter.</li>
	*	<li>string <b>subject</b> message subject.</li>
	*	<li>string <b>note</b> notes.</li>
	*	<li>int|null <b>creationDate</b> creation date/time.</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function GetNewsletters($listID) {
		$newsletters = array();
		$response = $this->execSoap(self::SOAPCLIENT_SEND, 'GetNewsletters', array('accessKey' => self::getSoapAccessKey(), 'listID' => $listID));
		foreach($response->list->newsletters->newsletter as $newsletter) {
			$newsletters[] = self::xmlToArray($newsletter, array('newsletterID' => 'int>id', 'subject' => 'string', 'note' => 'string', 'creationdate' => 'datetime>creationDate'));
		}
		return $newsletters;
	}
	/** Retrieve the code (body) of a newsletter.
	* @param int $listID The id of the list.
	* @param int $newsletterID The if of the newsletter.
	* @return array Array with these keys:<ul>
	*	<li>string <b>subject</b> The subject of the newsletter</li>
	*	<li>string <b>header</b> header</li>
	*	<li>string <b>body</b> body</li>
	*	<li>string <b>code</b> The code of the body of the newsletter</li>
	* </ul>
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function GetNewsletterCode($listID, $newsletterID) {
		$response = $this->execSoap(self::SOAPCLIENT_SEND, 'GetNewsletterCode', array('accessKey' => self::getSoapAccessKey(), 'listID' => $listID, 'newsletterID' => $newsletterID, 'isTemplate' => false));
		unset($response->listID, $response->newsletterID);
		$code = self::xmlToArray($response, array('newsletterSubject' => 'string>subject', 'newsletterHeader' => 'string>header', 'newsletterBody' => 'string>body', 'newsletterCode' => 'string>code'));
		if(!(strlen($code['subject']) || strlen($code['body']) || strlen($code['code']))) {
			return null;
		}
		return $code;
	}
	public function GetReportByMessage($listID, $messageID) {
		$response = $this->execSoap(self::SOAPCLIENT_REPORT, 'ReportByMessageEN', array('accessKey' => self::getSoapAccessKey(), 'listID' => $listID, 'messageID' => $messageID));
		$report = array();
		$report['click'] = array('total' => 0, 'url' => array());
		foreach($response->Clicks as $clicks) {
			$Url = (string)$clicks['Url'];
			$Total = @intval((string)$clicks['Total']);
			$report['click']['total'] += $Total;
			$report['click']['url'][$Url] = array('total' => $Total, 'email' => array());
			foreach($clicks as $click) {
				$report['click']['url'][$Url]['email'][(string)$click['Email']] = @intval((string)$click['Total']);
			}
		}
		$opens = $response->Opens;
		$report['open'] = array('total' => @intval((string)$opens['Total']), 'email' => array());
		foreach($opens->Open as $open) {
			$report['open']['email'][(string)$open['Email']] = @intval((string)$open['Total']);
		}
		return $report;
	}
	/** Retrieves all the lists and groups.
	* @return array Returns a list of MailUp lists, each one with the following keys:<ul>
	*	<li>int <b>id</b> the id of the list</li>
	*	<li>string <b>name</b> the name of the list</li>
	*	<li>array <b>groups</b> the list of MailUp groups, each one with the following keys:<ul>
	*		<li>int <b>id</b> the id of the group</li>
	*		<li>string <b>name</b> the name of the group</li>
	*	</ul></li>
	* </ul>
	* @throws Exception Throws an Exception in case of errros.
	*/
	public function GetListsAndGroups() {
		$body = $this->execSoap(self::SOAPCLIENT_IMPORT, 'GetNlLists', array(), $this->getAuthorizationHeader());
		$lists = array();
		foreach($body->Lists->List as $xList) {
			$list = self::xmlToArray($xList, array('@idList' => 'int>id', '@listGUID' => 'string>guid', '@listName' => 'string>name'));
			unset($list['Groups']);
			$list['groups'] = array();
			foreach($xList->Groups->Group as $xGroup) {
				$list['groups'][] = self::xmlToArray($xGroup, array('@idGroup' => 'int>id', '@groupName' => 'string>name'));
			}
			$lists[] = $list;
		}
		return $lists;
	}
	public function CreateGroup($idList, $newGroupName) {
		if(strlen($newGroupName) > 50) {
			throw new Exception('Maximum length of newGroupName is 50 characters');
		}
		$list = false;
		foreach($this->GetListsAndGroups() as $l) {
			if($l['id'] == $idList) {
				foreach($l['groups'] as $g) {
					if(strcasecmp($newGroupName, $g['name']) == 0) {
						throw new Exception("A group named '{$g['name']}' already exists in the list '{$l['name']}'");
					}
				}
				$list = $l;
				break;
			}
		}
		if(!$list) {
			throw new Exception("Unable to find the list with id $idList");
		}
		$body = $this->execSoap(self::SOAPCLIENT_IMPORT, 'CreateGroup', array('idList' => $idList, 'listGUID' => $list['guid'], 'newGroupName' => $newGroupName), $this->getAuthorizationHeader(), $idGroup);
		return array('id' => $idGroup, 'name' => $newGroupName);
	}
	/**
	* @param int $idList
	* @param int|array[int]|string $idGroups
	* @param array|string $data Possible types:<ul>
	*	<li><b>string</b> one email or single phone nomber per row (new line separators: one of \n, \r\n, \n)</li>
	*	<li><b>array</b> each item may be:
	*		<ul>
	*			<li><b>string</b> one email or single phone nomber per row (new line separators: one of \n, \r\n, \n)</li>
	*			<li><b>array</b> with the at least the keys <b>email</b> and/or <b>phone</b> (and optionally <b>prefix</b>). You can also specify <b>name</b> and/or <b>numeric fields (for custom field identifiers).</li>
	*		</ul>
	* </ul>
	* @param array $options Keys:<ul>
	*	<li>int <b>importType</b> [default: auto-detect] One of the MailUp::IMPORTTYPE_ constants</li>
	*	<li>int <b>mobileInputType</b> [default: auto-detect] One of the MailUp::MOBILEIMPORTTYPE_ constants</li>
	*	<li>bool <b>asPending</b> [default: false] subscribes users as pending</li>
	*	<li>bool <b>asOptOut</b> [default: false] imports users as unsubscribed</li>
	*	<li>bool <b>forceOptIn</b> [default: false] forces subscription of users</li>
	*	<li>bool <b>replaceGroups</b> [default: false] replaces groups</li>
	*	<li>bool <b>confirmEmail</b> [default: false] sends confirmation request email</li>
	*	<li>int <b>idConfirmNL</b> [default: empty] confirmation newsletter ID (if not specified or empty: confirmation request created automatically)</li>
	* </ul>
	* @return int Returns the id of the newly created import process
	* @throws Exception Throws an Exception in case of errors
	*/
	public function NewImportProcess($idList, $idGroups, $data, $options = array()) {
		$send = array();
		$list = false;
		foreach($this->GetListsAndGroups() as $l) {
			if($l['id'] == $idList) {
				$list = $l;
				break;
			}
		}
		if(!$list) {
			throw new Exception("Unable to find the list with id $idList");
		}
		$send['idList'] = $list['id'];
		$send['listGUID'] = $list['guid'];
		$send['idGroups'] = array();
		if(is_array($idGroups)) {
			$idGroups = implode('$idGroups', ',');
		}
		$send['idGroups'] = array();
		foreach(explode(',', (string)$idGroups) as $idGroup) {
			$idGroup = trim($idGroup);
			if(strlen($idGroup)) {
				if((!is_numeric($idGroup)) || (($i = @intval($idGroup)) <= 0)) {
					throw new Exception("Invalid group id: $idGroup");
				}
				$idGroup = $i;
				if(array_search($idGroup, $send['idGroups']) === false) {
					$group = false;
					foreach($list['groups'] as $g) {
						if($idGroup == $g['id']) {
							$group = $g;
							break;
						}
					}
					if(!$group) {
						throw new Exception("Invalid group id: $idGroup");
					}
					$send['idGroups'][] = $idGroup;
				}
			}
		}
		$send['idGroups'] = implode(',', $send['idGroups']);
		if(!is_array($options)) {
			$options = array();
		}
		if(isset($options['importType'])) {
			switch(@intval($options['importType'])) {
				case self::IMPORTTYPE_EMAIL:
				case self::IMPORTTYPE_SMS:
				case self::IMPORTTYPE_SMSEMAIL:
					$send['importType'] = @intval($options['importType']);
					break;
				default:
					throw new Exception("Invalid importType option: {$options['importType']}");
			}
		}
		if(isset($options['mobileInputType'])) {
			switch(@intval($options['mobileInputType'])) {
				case self::MOBILEIMPORTTYPE_MERGED:
				case self::MOBILEIMPORTTYPE_PREFIX_NUMBER:
					$send['mobileInputType'] = @intval($options['mobileInputType']);
					break;
				default:
					throw new Exception("Invalid mobileInputType option: {$options['mobileInputType']}");
			}
		}
		foreach(array('asPending' => 'asPending', 'asOptOut' => 'asOptOut', 'forceOptIn' => 'forceOptIn', 'replaceGroups' => 'replaceGroups', 'confirmEmail' => 'ConfirmEmail') as $in => $out) {
			$send[$out] = empty($options[$in]) ? false : true;
		}
		if(empty($options['idConfirmNL'])) {
			$send['idConfirmNL'] = 0;
		}
		elseif(!is_numeric($options['idConfirmNL'])) {
			throw new Exception("Invalid idConfirmNL option: {$options['idConfirmNL']}");
		}
		else {
			$options['idConfirmNL'] = intval($options['idConfirmNL']);
		}
		if(is_string($data)) {
			$data = explode("\n", str_replace("\r", "\n", str_replace("\r\n", "\n", $data)));
		}
		elseif(!is_array($data)) {
			throw new Exception('Invalid type of data parameter');
		}
		$dataOut = array();
		$customFieldIds = array();
		$guessedImportType = array(self::IMPORTTYPE_EMAIL => 0, self::IMPORTTYPE_SMS => 0);
		$singleGuess = false;
		$somePhonePrefix = false;
		foreach($data as $item) {
			$itemOut = array('email' => '', 'Prefix' => '', 'Number' => '', 'Name' => '', '_ids' => array());
			if(is_array($item)) {
				if(empty($item)) {
					continue;
				}
				foreach($item as $prop => $value) {
					if(is_numeric($prop)) {
						$id = intval($prop);
						if(array_search($id, $customFieldIds) === false) {
							$customFieldIds[] = $id;
						}
						$itemOut['_ids'][$id] = $value;
					}
					else {
						switch($prop) {
							case 'email':
								if(strlen($value)) {
									$guessedImportType[self::IMPORTTYPE_EMAIL]++;
									$itemOut['email'] = $value;
								}
								break;
							case 'phone':
								if(strlen($value)) {
									$guessedImportType[self::IMPORTTYPE_SMS]++;
									$itemOut['Number'] = $value;
								}
								break;
							case 'prefix':
								if(strlen($value)) {
									$guessedImportType[self::IMPORTTYPE_SMS]++;
									$somePhonePrefix = true;
									$itemOut['Prefix'] = $value;
								}
								break;
							case 'name':
								$itemOut['Name'] = $value;
								break;
							default:
								throw new Exception("Unknown property name: $prop");
						}
					}
				}
			}
			else {
				$item = (string)$item;
				if(!strlen($item)) {
					continue;
				}
				$singleGuess = true;
				if(isset($send['importType'])) {
					switch($send['importType']) {
						case self::IMPORTTYPE_EMAIL:
							$hasEmail = true;
							$itemOut['email'] = $item;
							break;
						case self::IMPORTTYPE_SMS:
							$hasPhone = true;
							$itemOut['Number'] = $item;
							break;
						default:
							throw new Exception('If $data is an array of strings, the importType option can be only MailUp::IMPORTTYPE_EMAIL or MailUp::IMPORTTYPE_SMS');
					}
				}
				else {
					$itemOut['guess'] = $item;
					if(preg_match('/\w@\w/', $item)) {
						$guessedImportType[self::IMPORTTYPE_EMAIL]++;
					}
					if(preg_match('/\d\d\d/', $item)) {
						$guessedImportType[self::IMPORTTYPE_SMS]++;
					}
				}
			}
			$dataOut[] = $itemOut;
		}
		if(empty($dataOut)) {
			throw new Exception('No data to send');
		}
		if(!isset($send['importType'])) {
			if($singleGuess) {
				$delta = $guessedImportType[self::IMPORTTYPE_EMAIL] - $guessedImportType[self::IMPORTTYPE_SMS];
				if($delta > 0) {
					$send['importType'] = self::IMPORTTYPE_EMAIL;
				}
				elseif($delta < 0) {
					$send['importType'] = self::IMPORTTYPE_SMS;
				}
				else {
					throw new Exception('Unable to guess the import type');
				}
				foreach(array_keys($dataOut) as $i) {
					if(array_key_exists('guess', $dataOut[$i])) {
						switch($send['importType']) {
							case self::IMPORTTYPE_EMAIL:
								$dataOut[$i]['email'] = $dataOut[$i]['guess'];
								break;
							case self::IMPORTTYPE_SMS:
								$dataOut[$i]['email'] = $dataOut[$i]['guess'];
								break;
							default:
								throw new Exception('This should never happen');
						}
						unset($dataOut[$i]['guess']);
					}
				}
			}
			else {
				if($guessedImportType[self::IMPORTTYPE_EMAIL] && $guessedImportType[self::IMPORTTYPE_SMS]) {
					$send['importType'] = self::IMPORTTYPE_SMSEMAIL;
				}
				elseif($guessedImportType[self::IMPORTTYPE_EMAIL]) {
					$send['importType'] = self::IMPORTTYPE_EMAIL;
				}
				elseif($guessedImportType[self::IMPORTTYPE_SMS]) {
					$send['importType'] = self::IMPORTTYPE_SMS;
				}
				else {
					throw new Exception('Unable to guess the import type');
				}
			}
		}
		if(!isset($send['mobileInputType'])) {
			$send['mobileInputType'] = $somePhonePrefix ? self::MOBILEIMPORTTYPE_PREFIX_NUMBER : self::MOBILEIMPORTTYPE_MERGED;
		}
		$xDoc = new SimpleXMLElement('<?xml version="1.0" standalone="yes"?><foo />', LIBXML_NOCDATA);
		$xSubscribers = $xDoc->addChild('subscribers');
		sort($customFieldIds);
		foreach($dataOut as $itemOut) {
			$xItem = $xSubscribers->addChild('subscriber');
			foreach($itemOut as $prop => $value) {
				switch($prop) {
					case '_ids':
						break;
					default:
						$xItem->addAttribute($prop, $value);
						break;
				}
			}
			foreach($customFieldIds as $customFieldId) {
				$xItem->addChild("campo$customFieldId", isset($itemOut['_ids'][$customFieldId]) ? $itemOut['_ids'][$customFieldId] : '');
			}
		}
		$send['xmlDoc'] = $xSubscribers->asXML();
		$this->execSoap(self::SOAPCLIENT_IMPORT, 'NewImportProcess', $send, $this->getAuthorizationHeader(), $idProcess);
		return $idProcess;
	}
	public function StartImportProcess($idList, $idProcess) {
		$send = array();
		$list = false;
		foreach($this->GetListsAndGroups() as $l) {
			if($l['id'] == $idList) {
				$list = $l;
				break;
			}
		}
		if(!$list) {
			throw new Exception("Unable to find the list with id $idList");
		}
		$send['idList'] = $list['id'];
		$send['listGUID'] = $list['guid'];
		$send['idProcess'] = $idProcess;
		$this->execSoap(self::SOAPCLIENT_IMPORT, 'StartProcess', $send, $this->getAuthorizationHeader());
	}
	public function GetImportProcessDetails($idList, $idProcess) {
		$send = array();
		$list = false;
		foreach($this->GetListsAndGroups() as $l) {
			if($l['id'] == $idList) {
				$list = $l;
				break;
			}
		}
		if(!$list) {
			throw new Exception("Unable to find the list with id $idList");
		}
		$send['idList'] = $list['id'];
		$send['listGUID'] = $list['guid'];
		$send['idProcess'] = $idProcess;
		$body = $this->execSoap(self::SOAPCLIENT_IMPORT, 'GetProcessDetails', $send, $this->getAuthorizationHeader());
		return self::xmlToArray(
			$body->ImportProcess,
			array(
				'@idProcess' => 'int>id',
				'StartDate' => 'datetime>startDate',
				'EndDate' => 'datetime>endDate',
				'TotalContacts' => 'int>totalContacts',
				'NewEmail' => 'int>newEmail',
				'ExistingEmail' => 'int>existingEmail',
				'OptOutEmail' => 'int>optOutEmail',
				'NewMobile' => 'int>newMobile',
				'ExistingMobile' => 'int>existingMobile',
				'OptOutMobile' => 'int>outOutMobile',
				'StatusCode' => 'int>status',
				'IsRunning' => 'bool>running',
				'ConfirmationEmail' => 'bool>confirmationEmail',
				'ConfirmationSent' => 'bool>confirmationSent'
			)
		);
	}
	/** Returns message recipients/views/bounces/unsubscriptions/clicks count
	* @param int $idMessage
	* @param string $which 'Recipients' or 'Views' or 'Bounces' or 'Unsubscriptions' or 'Clicks'
	* @return int
	*/
	public function getNewsletterUsersCount($idMessage, $which) {
		if(!is_int($idMessage)) {
			$idMessage = is_numeric($idMessage) ? @intval($idMessage) : 0;
		}
		if($idMessage <= 0) {
			throw new Exception('The message specified message id is wrong');
		}
		if(is_string($which)) {
			switch($which) {
				case 'Recipients':
				case 'Views':
				case 'Bounces':
				case 'Unsubscriptions':
				case 'Clicks':
					return $this->execRest(self::RESTENDPOINT_STATISTICS, "Message/$idMessage/Count/$which");
			}
		}
		throw new Exception('Invalid $which value in call to ' . __FUNCTION__ . ': ' . $which);
	}
	/** Returns message recipients/views/bounces/unsubscriptions/clicks list
	* @param int $idMessage
	* @param string $which 'Recipients' or 'Views' or 'Bounces' or 'Unsubscriptions' or 'Clicks'
	* @param int $pageNumber = 0 Page to retrieve (starts from 0)
	* @param int $pageSize = 20 Page size
	* @return array Keys:<ul>
	*	<li>bool <b>IsPaginated</b></li>
	*	<li>int <b>PageNumber</b> starting from 0</li>
	*	<li>int <b>PageSize</b></li>
	*	<li>int <b>Skipped</b></li>
	*	<li>int <b>TotalElementsCount</b></li>
	*	<li>array <b>Items</b> a list of arrays, each one with these keys:<ul>
	*		<li>string <b>Email</b></li>
	*		<li>int <b>IdRecipient</b></li>
	*		<li><i>for Bounces</i>: string <b>Type</b>. See http://assistenza.mailup.it/KB/a262/i-would-like-more-details-on-the-different-types-of-bounces.aspx</li>
	*		<li><i>for Clicks</i>: int <b>Count</b></li>
	*	</ul></li>
	* </ul>
	*/
	public function getNewsletterUsersList($idMessage, $which, $pageNumber = 0, $pageSize = 20) {
		if(!is_int($idMessage)) {
			$idMessage = is_numeric($idMessage) ? @intval($idMessage) : 0;
		}
		if($idMessage <= 0) {
			throw new Exception('The message specified message id is wrong');
		}
		$i = is_int($pageNumber) ? $pageNumber : (is_numeric($pageNumber) ? @intval($pageNumber) : -1);
		if($i < 0) {
			throw new Exception('Invalid $pageNumber value in call to ' . __FUNCTION__ . ': ' . $pageNumber);
		}
		$pageNumber = $i;
		$i = is_int($pageSize) ? $pageSize : (is_numeric($pageSize) ? @intval($pageSize) : -1);
		if($i <= 0) {
			throw new Exception('Invalid $pageSize value in call to ' . __FUNCTION__ . ': ' . $pageSize);
		}
		$pageSize = $i;
		if(strlen(is_string($which))) {
			switch($which) {
				case 'Recipients':
				case 'Views':
				case 'Bounces':
				case 'Unsubscriptions':
				case 'Clicks':
					$list = $this->execRest(self::RESTENDPOINT_STATISTICS, "Message/$idMessage/List/$which?pageSize=$pageSize&pageNumber=$pageNumber");
					if(!empty($list['Items'])) {
						foreach(array_keys($list['Items']) as $i) {
							unset($list['Items'][$i]['IdMessage']);
							$list['Items'][$i]['Email'] = (isset($list['Items'][$i]['Email']) && is_string($list['Items'][$i]['Email'])) ? trim($list['Items'][$i]['Email']) : '';
							$list['Items'][$i]['IdRecipient'] = (isset($list['Items'][$i]['IdRecipient']) && is_numeric($list['Items'][$i]['IdRecipient'])) ? @intval($list['Items'][$i]['IdRecipient']) : 0;
							switch($which) {
								case 'Bounces':
									$list['Items'][$i]['Type'] = (isset($list['Items'][$i]['Type']) && is_string($list['Items'][$i]['Type'])) ? trim($list['Items'][$i]['Type']) : '';
									break;
								case 'Clicks':
									$list['Items'][$i]['Count'] = (isset($list['Items'][$i]['Count']) && is_numeric($list['Items'][$i]['Count'])) ? @intval($list['Items'][$i]['Count']) : 0;
									break;
							}
						}
					}
					return $list;
			}
		}
		throw new Exception('Invalid $which value in call to ' . __FUNCTION__ . ': ' . $which);
	}
}

/** Base exception related to the MailUp instance. */
abstract class MailUpException extends Exception {
}

/** Exception associated to MailUpSend or MailUpReport. */
class MailUpSRException extends MailUpException {
	/**
	* @param SimpleXmlElement $xmlResponse
	* @param MailUp $instance
	* @throw MailUpSRException
	*/
	public static function checkSoapResponse($xmlResponse, $instance = null) {
		$errorCode = @trim((string)$xmlResponse->errorCode);
		if(!strlen($errorCode)) {
			throw new MailUpSRException('Missing error code');
		}
		if(!is_numeric($errorCode)) {
			throw new MailUpSRException(sprintf('Invalid error code: %s', $errorCode));
		}
		$errorCode = intval($errorCode);
		if($errorCode != 0) {
			$errorDescription = (string)$xmlResponse->errorDescription;
			if(!strlen($errorDescription)) {
				$errorDescription = sprintf('Error %s', $errorCode);
			}
			throw new MailUpSRException($errorDescription, $errorCode);
		}
	}
}
/** Exception associated to MailUpImport. */
class MailUpImportException extends MailUpException {
	/** Operation succeeded.
	* @var int
	*/
	const OK = 0;
	/** Missing return code.
	* @var int
	*/
	const RETURNCODE_MISSING = 1;
	/** Invalid return code.
	* @var int
	*/
	const RETURNCODE_INVALID = 2;
	/** Unrecognized error.
	* @var int
	*/
	const UNKNOWN_ERROR = -200;
	/** Unrecognized create group error.
	* @var int
	*/
	const CREATEGROUP_UNKNOWN_ERROR = -300;
	/** Lhe list has not been specified.
	* @var int
	*/
	const CREATEGROUP_MISSING_LIST = -301;
	/** The group name has not been specified.
	* @var int
	*/
	const CREATEGROUP_MISSING_GROUPNAME = -302;
	/** The group already exists.
	* @var int
	*/
	const CREATEGROUP_GROUP_ALREADY_EXISTS = -303;
	/** Unrecognized error in creating import process.
	* @var int
	*/
	const CREATEIMPORTPROCESS_UNKNOWN_ERROR = -400;
	/** xmlDoc parameter is empty.
	* @var int
	*/
	const CREATEIMPORTPROCESS_MISSING_XMLDOC = -401;
	/** Conversion from xml to csv failed.
	* @var int
	*/
	const CREATEIMPORTPROCESS_CONVERSION_FAILED = -402;
	/** Create new import process failed.
	* @var int
	*/
	const CREATEIMPORTPROCESS_FAILED = -403;
	/** Cannot create confirmation email.
	* @var int
	*/
	const CREATEIMPORTPROCESS_CONFIRMATION_NOT_CREATED = -410;
	/** ListsIDs and listsGUIDs must contain the same number of elements.
	* @var int
	*/
	const CREATEIMPORTPROCESS_MISMATCH_LIST_ID_GUID = -450;
	/** Unrecognized error.
	* @var int
	*/
	const IMPORTPROCESS_DETAILS_UNKNOWN_ERROR = -500;
	/** idProcess not found.
	* @var int
	*/
	const IMPORTPROCESS_DETAILS_NOT_FOUND = -501;
	/** Unrecognized import process error.
	* @var int;
	*/
	const IMPORTPROCESS_UNKNOWN_ERROR = -600;
	/** An import process is already running for the list.
	* @var int
	*/
	const IMPORTPROCESS_ALREADY_RUNNING = -601;
	/** An import process is already running for a different list.
	* @var int
	*/
	const IMPORTPROCESS_DIFFERENT_LIST = -602;
	/** Error checking the import process status.
	* @var int;
	*/
	const IMPORTPROCESS_ERROR_CHECKING_STATUS = -603;
	/** Error starting the import process job.
	* @var int;
	*/
	const IMPORTPROCESS_ERROR_STARTING = -604;
	public static function getErrorDescription($returnCode) {
		switch(@intval((string)$returnCode)) {
			case self::OK:
				return 'Operation succeeded.';
			case self::RETURNCODE_MISSING:
				return 'Missing return code.';
			case self::RETURNCODE_INVALID:
				return 'Invalid return code.';
			case self::UNKNOWN_ERROR:
				return 'Unrecognized error.';
			case self::CREATEGROUP_UNKNOWN_ERROR:
				return 'Unrecognized create group error.';
			case self::CREATEGROUP_MISSING_LIST:
				return 'Lhe list has not been specified.';
			case self::CREATEGROUP_MISSING_GROUPNAME:
				return 'The group name has not been specified.';
			case self::CREATEGROUP_GROUP_ALREADY_EXISTS:
				return 'The group already exists.';
			case self::CREATEIMPORTPROCESS_UNKNOWN_ERROR:
				return 'Unrecognized error in creating import process.';
			case self::CREATEIMPORTPROCESS_MISSING_XMLDOC:
				return 'xmlDoc parameter is empty.';
			case self::CREATEIMPORTPROCESS_CONVERSION_FAILED:
				return 'Conversion from xml to csv failed. ';
			case self::CREATEIMPORTPROCESS_FAILED:
				return 'Create new import process failed.';
			case self::CREATEIMPORTPROCESS_CONFIRMATION_NOT_CREATED:
				return 'Cannot create confirmation email.';
			case self::CREATEIMPORTPROCESS_MISMATCH_LIST_ID_GUID:
				return 'ListsIDs and listsGUIDs must contain the same number of elements.';
			case self::IMPORTPROCESS_DETAILS_UNKNOWN_ERROR:
				return 'Unrecognized error.';
			case self::IMPORTPROCESS_DETAILS_NOT_FOUND:
				return 'idProcess not found.';
			case self::IMPORTPROCESS_UNKNOWN_ERROR:
				return 'Unrecognized import process error.';
			case self::IMPORTPROCESS_ALREADY_RUNNING:
				return 'An import process is already running for the list.';
			case self::IMPORTPROCESS_DIFFERENT_LIST:
				return 'An import process is already running for a different list.';
			case self::IMPORTPROCESS_ERROR_CHECKING_STATUS:
				return 'Error checking the import process status.';
			case self::IMPORTPROCESS_ERROR_STARTING:
				return 'Error starting the import process job.';
			default:
				return sprintf('Unknown error code: %s', (string)$returnCode);
		}
	}
	public static function fromReturnCode($returnCode, $debugInfo = '') {
		return new MailUpImportException(self::getErrorDescription($returnCode) . $debugInfo, @intval((string)$returnCode));
	}
	/**
	* @param SimpleXmlElement $body
	* @param MailUp $instance
	* @return int
	* @throw MailUpImportException
	*/
	public static function checkSoapResponseBody($body, $instance = null) {
		$returnCode = @trim((string)$body->ReturnCode);
		if(!strlen($returnCode)) {
			throw self::fromReturnCode(self::RETURNCODE_MISSING);
		}
		if(!is_numeric($returnCode)) {
			throw self::fromReturnCode(self::RETURNCODE_INVALID);
		}
		$returnCode = @intval($returnCode);
		if($returnCode < 0) {
			throw self::fromReturnCode($returnCode, ($instance && $instance->debug) ? $instance->getDebugInfo(MailUp::SOAPCLIENT_IMPORT) : '');
		}
		return $returnCode;
	}
}
