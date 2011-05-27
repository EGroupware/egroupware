<?php
/**
 * eGroupWare - XmlHTTP (Ajax) server
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Lars Kneschke
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

// include our common functions, to have mbstring.func_overload functions available
require_once('./phpgwapi/inc/common_functions.inc.php');
require_once('./phpgwapi/inc/xajax/xajax_core/xajax.inc.php');

/**
 * callback if the session-check fails, redirects via xajax to login.php
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow anon access and anon_account is set, a sessionid or false otherwise
 */
function xajax_redirect(&$anon_account)
{
	// now the header is included, we can set the charset
	$GLOBALS['xajax']->configure('characterEncoding',translation::charset());
	define('XAJAX_DEFAULT_CHAR_ENCODING',translation::charset());

	$response = new xajaxResponse();
	$response->redirect($GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10');
	$response->printOutput();

	common::egw_exit();
}

/**
 * Exception handler for xajax, return the message (and trace, if enabled) as alert() to the user
 *
 * Does NOT return!
 *
 * @param Exception $e
 */
function ajax_exception_handler(Exception $e)
{
	// logging all exceptions to the error_log
	if (function_exists('_egw_log_exception'))
	{
		_egw_log_exception($e,$message);
	}
	$response = new xajaxResponse();
	$message .= ($message ? "\n\n" : '').$e->getMessage();

	// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
	if ($GLOBALS['egw_info']['server']['exception_show_trace'])
	{
		$message .= "\n\n".$e->getTraceAsString();
	}
	$response->addAlert($message);
	$response->printOutput();

	if (is_object($GLOBALS['egw']))
	{
		common::egw_exit();
	}
	exit;
}

// set our own exception handler, to not get the html from eGW's default one
set_exception_handler('ajax_exception_handler');

/**
 * Callback called from xajax
 *
 * Includs the header and set's up the eGW enviroment.
 *
 * @return xajaxResponse object
 */
function doXMLHTTP()
{
	$numargs = func_num_args();
	if($numargs < 1)
		return false;

	$argList	= func_get_args();
	$arg0		= array_shift($argList);

	//error_log("xajax_doXMLHTTP('$arg0',...)".print_r($argList,true));

	if (strpos($arg0,'::') !== false && strpos($arg0,'.') === false)	// static method name app_something::method
	{
		@list($className,$functionName,$handler) = explode('::',$arg0);
		list($appName) = explode('_',$className);
	}
	else
	{
		@list($appName, $className, $functionName, $handler) = explode('.',$arg0);
	}
	//error_log("xajax.php: appName=$appName, className=$className, functionName=$functionName, handler=$handler");

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'			=> $appName,
			'noheader'			=> True,
			'disable_Template_class'	=> True,
			'autocreate_session_callback' => 'xajax_redirect',
			'no_exception_handler' => true,	// we already installed our own
			'no_dla_update' => $appName == 'notifications',	// otherwise session never time out
		)
	);
	include('./header.inc.php');

	if(get_magic_quotes_gpc()) {
		$argList = array_stripslashes($argList);
	}

	// now the header is included, we can set the charset
	$GLOBALS['xajax']->configure('characterEncoding',translation::charset());
	define('XAJAX_DEFAULT_CHAR_ENCODING',translation::charset());

	switch($handler)
	{
		case '/etemplate/process_exec':
			$_GET['menuaction'] = $appName.'.'.$className.'.'.$functionName;
			$appName = $className = 'etemplate';
			$functionName = 'process_exec';
			$arg0 = 'etemplate.etemplate.process_exec';

			$argList = array(
				$argList[0]['etemplate_exec_id'],
				$argList[0]['submit_button'],
				$argList[0],
				'xajaxResponse',
			);
			//error_log("xajax_doXMLHTTP() /etemplate/process_exec handler: arg0='$arg0', menuaction='$_GET[menuaction]'");
			break;
		case 'etemplate':	// eg. ajax code in an eTemplate widget
			$arg0 = ($appName = 'etemplate').'.'.$className.'.'.$functionName;
			break;
	}
	if(substr($className,0,4) != 'ajax' && substr($className,-4) != 'ajax' &&
		$arg0 != 'etemplate.etemplate.process_exec' && substr($functionName,0,4) != 'ajax' ||
		!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+\.|::)[A-Za-z0-9_]+$/',$arg0))
	{
		// stopped for security reasons
		error_log($_SERVER['PHP_SELF']. ' stopped for security reason. '.$arg0.' is not valid. class- or function-name must start with ajax!!!');
		// send message also to the user
		throw new Exception($_SERVER['PHP_SELF']. ' stopped for security reason. '.$arg0.' is not valid. class- or function-name must start with ajax!!!');
		exit;
	}
	$ajaxClass =& CreateObject($appName.'.'.$className);
	$argList = translation::convert($argList, 'utf-8');
	if ($arg0 == 'etemplate.link_widget.ajax_search' && count($argList)==7)
	{
		$first = array_shift($argList);
		array_unshift($argList,'');
		array_unshift($argList,$first);
	}
	//error_log("xajax_doXMLHTTP('$arg0',...)".print_r($argList,true));
	return call_user_func_array(array(&$ajaxClass, $functionName), (array)$argList );
}
$xajax = new xajax();
//$xajax->configure('requestURI',$_SERVER['PHP_SELF']);
$xajax->register(XAJAX_FUNCTION,'doXMLHTTP');
$xajax->register(XAJAX_FUNCTION,'doXMLHTTP',array('mode' => "'synchronous'",'alias' => 'doXMLHTTPsync'));
$xajax->processRequest();
