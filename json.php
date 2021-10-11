<?php
/**
 * EGroupware - general JSON handler for EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 */

use EGroupware\Api;
use EGroupware\Api\Egw;
use EGroupware\Api\Json;

/**
 * callback if the session-check fails, redirects to login.php, if no valid basic auth credentials given
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean|string true if we allow anon access and anon_account is set, a sessionid or false otherwise
 */
function login_redirect(&$anon_account)
{
	// allow to make json calls via basic auth
	if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) &&
		($session_id = Api\Header\Authenticate::autocreate_session_callback($anon_account)))
	{
		return $session_id;
	}
	Json\Request::isJSONRequest(true);	// because Api\Json\Request::parseRequest() is not (yet) called
	$response = Json\Response::get();
	$response->apply('framework.callOnLogout');
	$response->redirect($GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10', true);

	// under PHP 8 the destructor is called to late and the response is not send
	$GLOBALS['egw']->__destruct();
	exit();
}

/**
 * Exception handler for xajax, return the message (and trace, if enabled) as alert() to the user
 *
 * Does NOT return!
 *
 * @param Exception|Error $e
 */
function ajax_exception_handler($e)
{
	// handle redirects without logging
	if (is_a($e, 'EGroupware\\Api\\Exception\\Redirect'))
	{
		Egw::redirect($e->url, $e->app);
	}
	// logging all exceptions to the error_log
	$message = null;
	if (function_exists('_egw_log_exception'))
	{
		_egw_log_exception($e,$message);
	}
	$response = Json\Response::get();
	$message .= ($message ? "\n\n" : '').$e->getMessage();

	$message .= "\n\n".$e->getFile().' ('.$e->getLine().')';
	// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
	if ($GLOBALS['egw_info']['server']['exception_show_trace'])
	{
		$message .= "\n\n".$e->getTraceAsString();
	}
	$response->message($message, 'error');

	// under PHP 8 the destructor is called to late and the response is not send
	$GLOBALS['egw']->__destruct();
	exit;
}

// set our own exception handler, to not get the html from eGW's default one
set_exception_handler('ajax_exception_handler');

try {
	if (!isset($_GET['menuaction']))
	{
		throw new InvalidArgumentException('Missing menuaction GET parameter', 998);
	}
	if (strpos($_GET['menuaction'],'::') !== false && strpos($_GET['menuaction'],'.') === false)	// static method name app_something::method
	{
		@list($className,$functionName,$handler) = explode('::',$_GET['menuaction']);

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
		@list($appName, $className, $functionName, $handler) = explode('.',$_GET['menuaction']);
	}
	//error_log("json.php: appName=$appName, className=$className, functionName=$functionName, handler=$handler");

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'			=> $appName,
			'noheader'		=> True,
			'disable_Template_class'	=> True,
			'autocreate_session_callback' => 'login_redirect',
			'no_exception_handler' => true,	// we already installed our own
			// only log ajax requests which represent former GET requests or submits
			// cuts down updates to egw_access_log table
			'no_dla_update' => !preg_match('/(Etemplate::ajax_process_content|\.jdots_framework\.ajax_exec\.template)/', $_GET['menuaction']),
		)
	);
	include_once('./header.inc.php');


	//Create a new json handler
	$json = new Json\Request();

	//Check whether the request data is set
	if (isset($GLOBALS['egw_unset_vars']['_POST[json_data]']))
	{
		$json->isJSONRequest(true);	// otherwise exception is not send back to client, as we have not yet called parseRequest()
		throw new Json\Exception\ScriptTags("JSON Data contains script tags. Aborting...");
	}
	// check if we have a real json request
	if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0)
	{
		$json->parseRequest($_GET['menuaction'], file_get_contents('php://input'));
	}
	else
	{
		$json->parseRequest($_GET['menuaction'], $_REQUEST['json_data']);
	}
	Json\Response::get();

	// run egw destructor now explicit, in case a (notification) email is send via Egw::on_shutdown(),
	// as stream-wrappers used by Horde Smtp fail when PHP is already in destruction
	$GLOBALS['egw']->__destruct();
}
// missing menuaction GET parameter or request:parameters object or unparsable JSON
catch (\InvalidArgumentException $e) {
	if (isset($json)) $json->isJSONRequest(false);	// no regular json request processing

	// give a proper HTTP status 400 Bad Request with some JSON payload explaining the problem
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode(array('error' => $e->getMessage(), 'errno' => $e->getCode()));
}
// other exceptions are handled by our ajax_exception_handler sending them back as alerts to client-side