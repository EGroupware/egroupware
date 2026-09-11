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

use EGroupware\Api\Etemplate\Widget;
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
	 * Check if class given in menuaction matches the specified app
	 *
	 * Prevent circumventing ACL/run-rights check, by specifying a different app e.g. api for autoloadable classes.
	 *
	 * @param string $menuaction
	 * @throws \InvalidArgumentException if a different app is specified than the also specified class belongs too
	 * @return void
	 */
	public static function checkMenuAction(string $menuaction)
	{
		[$appName, $className, $functionName, $handler] = explode('.', $menuaction)+[null, null, null, null];

		// ajax_exec call via menuaction=$app.kdots_framework.ajax_exec.template.$app.$class.$method
		if ($handler === 'template' &&
			preg_match('/^([^.]+)\.[a-z]+_framework\.ajax_exec\.template.([^.]+)\.([^.]+)\.(.+)$/', $menuaction, $matches) &&
			$matches[1] === $matches[2])
		{
			$className = $matches[3];
		}
		// check $className belongs to $appName
		if (str_starts_with($className, '\\EGroupware\\')) $className = substr($className, 1);  // remove leading \EGroupware
		$classApp = str_starts_with($className, 'EGroupware\\') ? explode('\\', $className)[1] :
			// handle special case of $appName containing '_' like 'news_admin'
			(str_starts_with($className, $appName.'_') ? $appName : explode('_', $className)[0]);

		if (strcasecmp($appName, $classApp) &&  // compare case-insensitive, as classnames are case-insensitive
			$appName !== 'admin' && // allow admin app, which anyway is the highest privilege (used a lot in Admin app)
			strtolower($classApp) !== 'api' &&  // api is implicit allowed for everyone
			// check of old not autoloadable classes e.g. phpbrain.uikb.$method
			!file_exists($path=EGW_INCLUDE_ROOT.'/'.$appName.'/inc/class.'.$classApp.'.inc.php'))
		{
			throw new \InvalidArgumentException("Class '$className' does NOT belong to application '$appName'!", 997);
		}
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
		if ($menuaction === 'api.queue')
		{
			// session is already closed early by json.php for every request now
			$responses = array();
			$response = Response::get();
			foreach($parameters[0] as $uid => $data)
			{
				//error_log("$uid: menuaction=$data[menuaction], parameters=".array2string($data['parameters']));
				try {
					$this->handleRequest($data['menuaction'], (array)$data['parameters']);
				}
				// one queued job failing must NOT abort the whole batch and strand every other
				// job's promise unresolved on the client - isolate it and report it as that job's
				// own response instead (see Jsonq.jsonqSend() "error" handling on the client side)
				catch (\Throwable $e)
				{
					$headline = null;
					if (function_exists('_egw_log_exception'))
					{
						_egw_log_exception($e, $headline);
					}
					$response->error($headline ? $headline."\n\n".$e->getMessage() : $e->getMessage());
				}
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
			@list($appName, $className, $functionName, $handler) = explode('.', $menuaction)+[null, null, null, null];

			self::checkMenuAction($menuaction);
		}

		// check if user has rights for appName (would otherwise not happen for api.queue!)
		if (!in_array($appName, ['api', 'about']) && !isset($GLOBALS['egw_info']['user']['apps'][$appName]))
		{
			throw new Api\Exception\NoPermission\App($appName);
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
		// method_exists() first: ReflectionMethod would throw a ReflectionException for a
		// misspelled method, hiding the real problem behind a stack trace from this file. Let it
		// fall through to the rejection below instead, which names the menuaction.
		if (strpos($menuaction,'::') !== false && strpos($menuaction,'.') === false &&
			method_exists($className, $functionName))
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

		// A menuaction naming a method the class does not have is a client-side mistake (typo, an
		// endpoint that was renamed or never existed), not a server fault. Rejected here, ahead of
		// the class being constructed, so that probing a bad menuaction cannot set off a
		// constructor's side effects. Only autoloadable classes can be checked this far up; the
		// rest are covered by the is_callable() check after they are built.
		if (!isset($template) && !isset($ajaxClass) && class_exists($className) &&
			!method_exists($className, $functionName))
		{
			throw self::invalidMenuaction($menuaction, $className, $functionName);
		}

		if (isset($template))
		{
			$ajaxClass = $GLOBALS['egw']->framework;
		}
		elseif (!isset($ajaxClass))
		{
			$ajaxClass = class_exists($className) ? new $className() : CreateObject($appName.'.'.$className);
		}

		// What the check above could not see: a legacy class only reachable via CreateObject(), the
		// framework object, and methods that do exist but are not public. Without this,
		// call_user_func_array() below raises an uncaught TypeError, which reaches the user as a
		// generic "An error happened!" plus this file's path and line, and says nothing about the
		// menuaction that is actually wrong.
		if (!is_callable([$ajaxClass, $functionName]))
		{
			throw self::invalidMenuaction($menuaction,
				is_object($ajaxClass) ? get_class($ajaxClass) : $className, $functionName);
		}

		// for Ajax: no need to load the "standard" javascript files,
		// they are already loaded, in fact jquery has a problem if loaded twice
		Api\Framework::js_files(array());

		call_user_func_array(array($ajaxClass, $functionName),
			Api\Translation::convert($parameters, 'utf-8'));
	}

	/**
	 * Log, and build the exception for, a menuaction naming a method that can not be called
	 *
	 * \InvalidArgumentException, not Exception\InvalidName: the latter is a NoPermission subclass,
	 * which would head the message with "Permission denied!" and point whoever reads it at ACL
	 * instead of at the typo. It also gets json.php's dedicated catch, which answers 400 with just
	 * this message - an uncaught throwable there would instead hand the client this file's path and
	 * line (and its whole trace, where exception_show_trace is on) via ajax_exception_handler().
	 *
	 * That catch logs nothing though, so the logging happens here, in the same shape as the
	 * security check above: one line naming what was asked for, rather than the stack trace the
	 * uncaught TypeError used to leave behind for the very same mistake.
	 *
	 * Callers must be past handleRequest()'s security check, which is what limits $menuaction (and
	 * with it $class and $method, cut from the same string) to [A-Za-z0-9_\\-] plus the '.'/'::'
	 * separators - so nothing quoted back here, into the response or the log, can carry markup or
	 * a line break.
	 *
	 * @param string $menuaction as requested, to name what has to be corrected
	 * @param string $class resolved class name, which is not always the one $menuaction spells
	 * @param string $method
	 * @return \InvalidArgumentException code 996, in the same series as checkMenuAction()'s 997
	 */
	private static function invalidMenuaction(string $menuaction, string $class, string $method) : \InvalidArgumentException
	{
		$message = $menuaction.' is not a valid menuaction: class '.$class.
			' has no callable method "'.$method.'"';

		error_log(($_SERVER['PHP_SELF'] ?? 'json.php').': '.$message);

		return new \InvalidArgumentException($message, 996);
	}
}

// Scan for widget classes and cache for 1 hour
Widget::scanForWidgets();