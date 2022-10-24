<?php
/**
 * EGroupware API: JSON - Contains functions and classes for doing JSON requests.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Andreas Stoeckel <as@stylite.de>
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Json;

use ReflectionMethod;
use EGroupware\Api;

// explicitly import old, not yet ported api classes
use notifications_push;

/**
 * Class handling JSON requests to the server
 */
class Request
{
	private static $_hadJSONRequest = false;

	/**
	 * Check if JSON request running or (re)set JSON request flag
	 *
	 * Can be used to:
	 * - detect regular JSON request:
	 *		Api\Json\Request::isJSONRequest()
	 * - switch regular JSON response handling off, which would send arbitrary output via response method "html".
	 *   Neccessary if json.php is used to send arbitrary JSON data eg. nodes for foldertree!
	 *		Api\Json\Request::isJSONRequest(false)
	 *
	 * @param boolean $set =null
	 * @return boolean
	 */
	public static function isJSONRequest($set=null)
	{
		$ret = self::$_hadJSONRequest;
		if (isset($set)) self::$_hadJSONRequest = $set;
		return $ret;
	}

	/**
	 * Parses the raw input data supplied with the input_data parameter and calls the menuaction
	 * passing all parameters supplied in the request to it.
	 *
	 * Also handle queued requests (menuaction == 'api.queue') containing multiple requests
	 *
	 * @param string menuaction to call
	 * @param string $input_data is the RAW input data as it was received from the client
	 * @throws \InvalidArgumentException if JSON can not be parsed (json_last_error())
	 *  or did not contain request[parameters] array (999)
	 */
	public function parseRequest($menuaction, $input_data)
	{
		// Remember that we currently are in a JSON request - e.g. used in the redirect code
		self::$_hadJSONRequest = true;

		// no or empty payload is eg. used by dynamicly loading tree nodes (uses just GET parameters)
		if (!isset($input_data) || $input_data === '')
		{
			$parameters = array();
		}
		else
		{
			if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) $input_data = stripslashes($input_data);

			if (($json_data = json_decode($input_data,true)) === null && json_last_error() !== JSON_ERROR_NONE)
			{
				throw new \InvalidArgumentException('JSON '.json_last_error_msg(), json_last_error());
			}
			elseif (is_array($json_data) && isset($json_data['request']) && isset($json_data['request']['parameters']) && is_array($json_data['request']['parameters']))
			{
				//error_log(__METHOD__.__LINE__.array2string($json_data['request']).function_backtrace());
				$parameters =& $json_data['request']['parameters'];
			}
			else
			{
				throw new \InvalidArgumentException('Missing request:parameters object', 999);
			}
		}
		// do we have a single request or an array of queued requests
		if ($menuaction == 'api.queue')
		{
			$responses = array();
			$response = Response::get();
			foreach($parameters[0] as $uid => $data)
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
		// check if we have push notifications, if notifications app available AND enabled for the user
		if (!empty($GLOBALS['egw_info']['user']['apps']['notifications']) &&
			class_exists('notifications_push'))
		{
			notifications_push::get();
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
			if (substr($className, 0, 11) == 'EGroupware\\')
			{
				list(,$appName) = explode('\\', strtolower($className));
			}
			else
			{
				list($appName) = explode('_',$className);
			}
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
			case 'et2_process':
				$menuaction = ($className=Api\Etemplate::class).'::'.($functionName='ajax_process_content');
				break;
			case 'template':	// calling current template / framework object
				$menuaction = $appName.'.'.$className.'.'.$functionName;
				$className = get_class($GLOBALS['egw']->framework);
				list($template) = explode('_', $className);
				break;
		}

		// Check for a real static method, avoid instantiation if it is
		if (strpos($menuaction,'::') !== false && strpos($menuaction,'.') === false)
		{
			$m = new ReflectionMethod($menuaction);
			if($m->isStatic())
			{
				$ajaxClass = $className;
			}
		}

		if(substr($className,0,4) != 'ajax' && substr($className,-4) != 'ajax' &&
			$menuaction != 'etemplate.etemplate.process_exec' && substr($functionName,0,4) != 'ajax' ||
			!preg_match('/^[A-Za-z0-9_\\\\-]+(\.[A-Za-z0-9_\\\\]+\.|::)[A-Za-z0-9_]+$/',$menuaction))
		{
			// stopped for security reasons
			error_log("className='$className', functionName='$functionName', menuaction='$menuaction'");
			error_log($_SERVER['PHP_SELF']. ' stopped for security reason. '.$menuaction.' is not valid. class- or function-name must start with ajax!!!');
			// send message also to the user
			throw new Exception\InvalidName($_SERVER['PHP_SELF']. ' stopped for security reason. '.$menuaction.' is not valid. class- or function-name must start with ajax!!!');
		}

		if (isset($template))
		{
			$ajaxClass = $GLOBALS['egw']->framework;
		}
		elseif (!isset($ajaxClass))
		{
			$ajaxClass = class_exists($className) ? new $className() : CreateObject($appName.'.'.$className);
		}

		// for Ajax: no need to load the "standard" javascript files,
		// they are already loaded, in fact jquery has a problem if loaded twice
		Api\Framework::js_files(array());

		call_user_func_array(array($ajaxClass, $functionName),
			Api\Translation::convert($parameters, 'utf-8'));


	}
}