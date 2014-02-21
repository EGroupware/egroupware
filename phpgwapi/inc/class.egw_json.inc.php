<?php
/**
 * EGroupware API: JSON - Contains functions and classes for doing JSON requests.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/**
 * Class handling JSON requests to the server
 */
class egw_json_request
{

	private static $_hadJSONRequest = false;

	public static function isJSONRequest()
	{
		return self::$_hadJSONRequest;
	}

	/**
	 * Parses the raw input data supplied with the input_data parameter and calls the menuaction
	 * passing all parameters supplied in the request to it.
	 *
	 * Also handle queued requests (menuaction == 'home.queue') containing multiple requests
	 *
	 * @param string menuaction to call
	 * @param string $input_data is the RAW input data as it was received from the client
	 */
	public function parseRequest($menuaction, $input_data)
	{
		// Remember that we currently are in a JSON request - e.g. used in the redirect code
		self::$_hadJSONRequest = true;

		if (get_magic_quotes_gpc()) $input_data = stripslashes($input_data);

		$json_data = json_decode($input_data,true);
		if (is_array($json_data) && isset($json_data['request']) && isset($json_data['request']['parameters']) && is_array($json_data['request']['parameters']))
		{
			//error_log(__METHOD__.__LINE__.array2string($json_data['request']).function_backtrace());
			$parameters =& $json_data['request']['parameters'];
		}
		else
		{
			$parameters = array();
		}
		// do we have a single request or an array of queued requests
		if ($menuaction == 'home.queue')
		{
			$responses = array();
			$response = egw_json_response::get();
			foreach($parameters as $uid => $data)
			{
				//error_log("$uid: menuaction=$data[menuaction], parameters=".array2string($data['parameters']));
				$this->handleRequest($data['menuaction'], (array)$data['parameters']);
				$responses[$uid] = $response->initResponseArray();
				//error_log("responses[$uid]=".array2string($responses[$uid]));
			}
			$response->data($responses);	// send all responses as data
		}
		else
		{
			$this->handleRequest($menuaction, $parameters);
		}
	}

	/**
	 * Request handler
	 *
	 * @param string $menuaction
	 * @param array $parameters
	 */
	public function handleRequest($menuaction, array $parameters)
	{
		if (strpos($menuaction,'::') !== false && strpos($menuaction,'.') === false)	// static method name app_something::method
		{
			@list($className,$functionName,$handler) = explode('::',$menuaction);
			list($appName) = explode('_',$className);
		}
		else
		{
			@list($appName, $className, $functionName, $handler) = explode('.',$menuaction);
		}
		//error_log("json.php: appName=$appName, className=$className, functionName=$functionName, handler=$handler");

		switch($handler)
		{
			case '/etemplate/process_exec':
				$_GET['menuaction'] = $appName.'.'.$className.'.'.$functionName;
				$appName = $className = 'etemplate';
				$functionName = 'process_exec';
				$menuaction = 'etemplate.etemplate.process_exec';

				$parameters = array(
					$parameters[0]['etemplate_exec_id'],
					$parameters[0]['submit_button'],
					$parameters[0],
					'xajaxResponse',
				);
				//error_log("xajax_doXMLHTTP() /etemplate/process_exec handler: arg0='$menuaction', menuaction='$_GET[menuaction]'");
				break;
			case 'etemplate':	// eg. ajax code in an eTemplate widget
				$menuaction = ($appName = 'etemplate').'.'.$className.'.'.$functionName;
				break;
			case 'template':	// calling current template / framework object
				$menuaction = $appName.'.'.$className.'.'.$functionName;
				$className = get_class($GLOBALS['egw']->framework);
				list($template) = explode('_', $className);
				break;
		}

		if(substr($className,0,4) != 'ajax' && substr($className,-4) != 'ajax' &&
			$menuaction != 'etemplate.etemplate.process_exec' && substr($functionName,0,4) != 'ajax' ||
			!preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_]+\.|::)[A-Za-z0-9_]+$/',$menuaction))
		{
			// stopped for security reasons
			error_log($_SERVER['PHP_SELF']. ' stopped for security reason. '.$menuaction.' is not valid. class- or function-name must start with ajax!!!');
			// send message also to the user
			throw new Exception($_SERVER['PHP_SELF']. ' stopped for security reason. '.$menuaction.' is not valid. class- or function-name must start with ajax!!!');
		}

		if (isset($template))
		{
			$ajaxClass = $GLOBALS['egw']->framework;
		}
		else
		{
			$ajaxClass = CreateObject($appName.'.'.$className);
		}

		// for Ajax: no need to load the "standard" javascript files,
		// they are already loaded, in fact jquery has a problem if loaded twice
		egw_framework::js_files(array());

		$parameters = translation::convert($parameters, 'utf-8');

		call_user_func_array(array($ajaxClass, $functionName), $parameters);
	}
}

/**
 * Class used to send ajax responses
 */
class egw_json_response
{
	/**
	 * A response can only contain one generic data part.
	 * This variable is used to store, whether a data part had already been added to the response.
	 *
	 * @var boolean
	 */
	private $hasData = false;

	/**
	 * Array containing all beforeSendData callbacks
	 */
	protected $beforeSendDataProcs = array();

	/**
	 * Holds the actual response data which is then encoded to JSON
	 * once the "getJSON" function is called
	 *
	 * @var array
	 */
	protected $responseArray = array();

	/**
	 * Holding instance of class for singelton egw_json_response::get()
	 *
	 * @var egw_json_response
	 */
	private static $response = null;

	/**
	 * Force use of singleton: $response = egw_json_response::get();
	 */
	private function __construct()
	{

	}

	/**
	 * Singelton for class
	 *
	 * @return egw_json_response
	 */
	public static function get()
	{
		if (!isset(self::$response))
		{
			self::$response = new egw_json_response();
			self::sendHeader();
		}
		return self::$response;
	}

	public static function isJSONResponse()
	{
		return isset(self::$response);
	}

	/**
	 * Do we have a JSON response to send back
	 *
	 * @return boolean
	 */
	public function haveJSONResponse()
	{
		return $this->responseArray || $this->beforeSendDataProcs;
	}

	/**
	 * Private function used to send the HTTP header of the JSON response
	 */
	private function sendHeader()
	{
		$file = $line = null;
		if (headers_sent($file, $line))
		{
			error_log(__METHOD__."() header already sent by $file line $line: ".function_backtrace());
		}
		else
		{
			//Send the character encoding header
			header('content-type: application/json; charset='.translation::charset());
		}
	}

	/**
	 * Private function which is used to send the result via HTTP
	 */
	public static function sendResult()
	{
		$inst = self::get();

		//Call each attached before send data proc
		foreach ($inst->beforeSendDataProcs as $proc)
		{
			call_user_func_array($proc['proc'], $proc['params']);
		}

		// check if application made some direct output
		if (($output = ob_get_clean()))
		{
			if (!$inst->haveJSONResponse())
			{
				error_log(__METHOD__."() adding output with inst->addGeneric('output', '$output')");
				$inst->addGeneric('html', $output);
			}
			else
			{
				$inst->alert('Application echoed something', $output);
			}
		}

		echo $inst->getJSON();
		$inst->initResponseArray();
	}

	/**
	 * xAjax compatibility function
	 */
	public function printOutput()
	{
		// do nothing, as output is triggered by egw::__destruct()
	}

	/**
	 * Adds any type of data to the response array
	 */
	protected function addGeneric($key, $data)
	{
		self::get()->responseArray[] = array(
			'type' => $key,
			'data' => $data,
		);
	}

	/**
	 * Init responseArray
	 *
	 * @param array $arr
	 * @return array previous content
	 */
	public function initResponseArray()
	{
		$return = $this->responseArray;
		$this->responseArray = $this->beforeSendDataProcs = array();
		$this->hasData = false;
		return $return;
	}

	/**
	 * Adds a "data" response to the json response.
	 *
	 * This function may only be called once for a single JSON response object.
	 *
	 * @param object|array|string $data can be of any data type and will be added JSON Encoded to your response.
	 */
	public function data($data)
	{
		/* Only allow adding the data response once */
		$inst = self::get();
		if (!$inst->hasData)
		{
			$inst->addGeneric('data', $data);
			$inst->hasData = true;
		}
		else
		{
			throw new Exception("Adding more than one data response to a JSON response is not allowed.");
		}
	}

	/**
	 * Adds an "alert" to the response which can be handeled on the client side.
	 *
	 * The default implementation simply displays the text supplied here with the JavaScript function "alert".
	 *
	 * @param string $message contains the actual message being sent to the client.
	 * @param string $details (optional) can be used to inform the user on the client side about additional details about the error. This might be information how the error can be resolved/why it was raised or simply some debug data.
	 */
	public function alert($message, $details = '')
	{
		if (is_string($message) && is_string($details))
		{
			$this->addGeneric('alert', array(
				"message" => $message,
				"details" => $details));
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	/**
	 * Allows to add a generic java script to the response which will be executed upon the request gets received.
	 *
	 * @deprecated
	 * @param string $script the script code which should be executed upon receiving
	 */
	public function script($script)
	{
		if (is_string($script))
		{
			$this->addGeneric('script', $script);
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	/**
	 * Allows to call a global javascript function with given parameters: window[$func].apply(window, $parameters)
	 *
	 * @param string $func name of the global (window) javascript function to call
	 * @param array $parameters=array()
	 */
	public function apply($function,array $parameters=array())
	{
		if (is_string($function))
		{
			$this->addGeneric('apply', array(
				'func'  => $function,
				'parms' => $parameters,
			));
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	/**
	 * Allows to call a global javascript function with given parameters: window[$func].call(window[, $param1[, ...]])
	 *
	 * @param string $func name of the global (window) javascript function to call
	 * @param mixed $parameters variable number of parameters
	 */
	public function call($function)
	{
		$parameters = func_get_args();
		array_shift($parameters);	// shift off $function

		if (is_string($function))
		{
			$this->addGeneric('apply', array(
				'func'  => $function,
				'parms' => $parameters,
			));
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	/**
	 * Allows to call a jquery function on a selector with given parameters: $j($selector).$func($parmeters)
	 *
	 * @param string $selector jquery selector
	 * @param string $method name of the jquery to call
	 * @param array $parameters=array()
	 */
	public function jquery($selector,$method,array $parameters=array())
	{
		if (is_string($selector) && is_string($method))
		{
			$this->addGeneric('jquery', array(
				'select' => $selector,
				'func'   => $method,
				'parms' => $parameters,
			));
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	public function generic($type, array $parameters = array())
	{
		if (is_string($type))
		{
			$this->addGeneric($type, $parameters);
		}
		else
		{
			throw new Exception("Invalid parameters supplied.");
		}
	}

	/**
	 * Adds an html assign to the response, which is excecuted upon the request is received.
	 *
	 * @param string $id id of dom element to modify
	 * @param string $key attribute name of dom element which should be modified
	 * @param string $value the value which should be assigned to the given attribute
	 */
	public function assign($id, $key, $value)
	{
		if (is_string($id) && is_string($key) && (is_string($value) || is_numeric($value) || is_null($value)))
		{
			$this->addGeneric('assign', array(
				'id' => $id,
				'key' => $key,
				'value' => $value,
			));
		}
		else
		{
			throw new Exception("Invalid parameters supplied");
		}
	}

	/**
	 * Redirect to given url
	 *
	 * @param string $url
	 * @param boolean $global specifies whether to redirect the whole framework
	 * @param string $app=null default current app from flags
	 * or only the current application
	 */
	public function redirect($url, $global = false, $app=null)
	{
		if (is_string($url) && is_bool($global))
		{
			//self::script("location.href = '$url';");
			$this->addGeneric('redirect', array(
				'url' => $url,
				'global' => $global,
				'app' => $app ? $app : $GLOBALS['egw_info']['flags']['currentapp'],
			));
		}
	}

	/**
	 * Displays an error message on the client
	 */
	public function error($msg)
	{
		if (is_string($msg))
		{
			$this->addGeneric('error', $msg);
		}
	}

	/**
	 * Includes the given CSS file. Every url can only be included once.
	 *
	 * @param string $url specifies the url to the css file to include
	 */
	public function includeCSS($url)
	{
		if (is_string($url))
		{
			$this->addGeneric('css', $url);
		}
	}

	/**
	 * Includes the given JS file. Every url can only be included once.
	 *
	 * @param string $url specifies the url to the css file to include
	 */
	public function includeScript($url)
	{
		if (is_string($url))
		{
			self::get()->addGeneric('js', $url);
		}
	}

	/**
	 * Returns the actual JSON code generated by calling the above "add" function.
	 *
	 * @return string
	 */
	public function getJSON()
	{
		$inst = self::get();

		/* Wrap the result array into a parent "response" Object */
		$res = array('response' => $inst->responseArray);

		return json_encode($res);	//PHP5.3+, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
	}

	public function json_encode($var)
	{
		$ret = json_encode($var);

		if (($err = json_last_error()))
		{
			static $json_err2str = array(
	        	JSON_ERROR_NONE => 'No errors',
	        	JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
	        	JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
	        	JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
	        	JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
	        	JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
	        );
			error_log(__METHOD__.'('.array2string($var).') json_last_error()='.$err.'='.$json_err2str[$err]);
		}
		return $ret;
	}

	/**
	 * Function which can be used to add an event listener callback function to
	 * the "beforeSendData" callback. This callback might be used to add a response
	 * which always has to be added after all other responses.
	 * @param callback Callback function or method which should be called before the response gets sent
	 * @param mixed n Optional parameters which get passed to the callback function.
	 */
	public function addBeforeSendDataCallback($proc)
	{
		//Get the current instance
		$inst = self::get();

		//Get all parameters passed to the function and delete the first one
		$params = func_get_args();
		array_shift($params);

		$inst->beforeSendDataProcs[] = array(
			'proc' => $proc,
			'params' => $params
		);
	}
}

/**
 * Deprecated legacy xajax wrapper functions for the new egw_json interface
 */
class xajaxResponse
{
	public function __call($name, $args)
	{
		if (substr($name, 0, 3) == 'add')
		{
			$name = substr($name, 3);
			$name[0] = strtolower($name[0]);
		}
		return call_user_func_array(array(egw_json_response::get(), $name), $args);
	}

	public function addScriptCall($func)
	{
		$args = func_get_args();
		$func = array_shift($args);

		return call_user_func(array(egw_json_response::get(), 'apply'), $func, $args);
	}

	public function getXML()
	{
		return '';
	}
}
