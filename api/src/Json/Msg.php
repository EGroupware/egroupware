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

/**
 * Abstract class implementing different type of JSON messages understood by client-side
 */
abstract class Msg
{
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
	 * Adds an "alert" to the response which can be handeled on the client side.
	 *
	 * The default implementation simply displays the text supplied here with the JavaScript function "alert".
	 *
	 * @param string $message contains the actual message being sent to the client.
	 * @param string $type =null (optional) "success", "error" or "info",
	 * null: check for "error" in message and use "error" in that case, otherwise "success"
	 */
	public function message($message, $type = null)
	{
		if (is_string($message) && (is_string($type) || is_null($type)))
		{
			$this->addGeneric('message', array(
				"message" => $message,
				"type" => $type));
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
	 * @param string $function name of the global (window) javascript function to call
	 * @param array $parameters =array()
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
	 * Allows to call a jquery function on a selector with given parameters: jQuery($selector).$func($parmeters)
	 *
	 * @param string $selector jquery selector
	 * @param string $method name of the jquery to call
	 * @param array $parameters =array()
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
	 * @param string $app =null default current app from flags
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
			$this->addGeneric('js', $url);
		}
	}

	/**
	 * Adds any type of data to the message
	 *
	 * @param string $key
	 * @param mixed $data
	 */
	abstract protected function addGeneric($key, $data);
}
