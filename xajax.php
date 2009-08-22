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

require_once('./phpgwapi/inc/xajax.inc.php');

/**
 * callback if the session-check fails, redirects via xajax to login.php
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow anon access and anon_account is set, a sessionid or false otherwise
 */
function xajax_redirect(&$anon_account)
{
	// now the header is included, we can set the charset
	$GLOBALS['xajax']->setCharEncoding($GLOBALS['egw']->translation->charset());
	define('XAJAX_DEFAULT_CHAR_ENCODING',$GLOBALS['egw']->translation->charset());

	$response = new xajaxResponse();
	$response->addScript("location.href='".$GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10'."';");

	header('Content-type: text/xml; charset='.$GLOBALS['egw']->translation->charset());
	echo $response->getXML();
	$GLOBALS['egw']->common->egw_exit();
}

/**
 * Exception handler for xajax, return the message (and trace) as alert() to the user
 *
 * Does NOT return!
 *
 * @param Exception $e
 */
function ajax_exception_handler(Exception $e)
{
	$response = new xajaxResponse();
	$response->addAlert($e->getMessage()."\n\n".$e->getTraceAsString());
	header('Content-type: text/xml; charset='.(is_object($GLOBALS['egw'])?$GLOBALS['egw']->translation->charset():'utf-8'));
	echo $response->getXML();
	if (is_object($GLOBALS['egw']))
	{
		$GLOBALS['egw']->common->egw_exit();
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
 * @return string with XML response from xajaxResponse::getXML()
 */
function doXMLHTTP()
{
	$numargs = func_num_args();
	if($numargs < 1)
		return false;

	$argList	= func_get_args();
	$arg0		= array_shift($argList);

	if(get_magic_quotes_gpc()) {
		foreach($argList as $key => $value) {
			if(is_array($value)) {
				foreach($argList as $key1 => $value1) {
					$argList[$key][$key1] = stripslashes($value1);
				}
			} else {
				$argList[$key] = stripslashes($value);
			}
		}
	}
	//error_log("xajax_doXMLHTTP('$arg0',...)");

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

	// now the header is included, we can set the charset
	$GLOBALS['xajax']->setCharEncoding($GLOBALS['egw']->translation->charset());
	define('XAJAX_DEFAULT_CHAR_ENCODING',$GLOBALS['egw']->translation->charset());

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
			error_log("xajax_doXMLHTTP() /etemplate/process_exec handler: arg0='$arg0', menuaction='$_GET[menuaction]'");
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
	$argList = $GLOBALS['egw']->translation->convert($argList, 'utf-8');

	return call_user_func_array(array(&$ajaxClass, $functionName), (array)$argList );
}

$xajax = new xajax($_SERVER['PHP_SELF']);
$xajax->registerFunction('doXMLHTTP');
$xajax->processRequests();
