<?php
/**
 * EGroupware exception handler and friends
 *
 * Usually loaded via header.inc.php or api/src/loader/common.php
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Translate message only if translation object is already loaded
 *
 * This function is useful for exception handlers or early stages of the initialisation of the egw object,
 * as calling lang would try to load the translations, evtl. cause more errors, eg. because there's no db-connection.
 *
 * @param string $key message in English with %1, %2, ... placeholders
 * @param string $vars =null multiple values to replace the placeholders
 * @return string translated message with placeholders replaced
 */
function try_lang($key,$vars=null)
{
	static $varnames = array('%1','%2','%3','%4');

	if(!is_array($vars))
	{
		$vars = func_get_args();
		array_shift($vars);	// remove $key
	}
	if (class_exists('EGroupware\Api\Translation',false))
	{
		try {
			return Api\Translation::translate($key, $vars);
		}
		catch (\Throwable $e) {
			// ignore
		}
	}
	return str_replace($varnames,$vars,$key);
}

/**
 * Classify exception for a headline and log it to error_log, if not running as cli
 *
 * @param Exception|Error $e
 * @param string &$headline
 */
function _egw_log_exception($e,&$headline=null)
{
	$trace = explode("\n", $e->getTraceAsString());
	if ($e instanceof Api\Exception\NoPermission)
	{
		$headline = try_lang('Permission denied!');
	}
	elseif ($e instanceof Api\Db\Exception)
	{
		$headline = try_lang('Database error');
	}
	elseif ($e instanceof Api\Exception\WrongUserinput)
	{
		$headline = '';	// message contains the whole message, it's usually no real error but some input validation
	}
	elseif ($e instanceof egw_exception_warning)
	{
		$headline = 'PHP Warning';
		array_shift($trace);
	}
	else
	{
		$headline = try_lang('An error happened');
	}
	// log exception to error log, if not running as cli,
	// which outputs the error_log to stderr and therefore output it twice to the user
	if(isset($_SERVER['HTTP_HOST']) || $GLOBALS['egw_info']['flags']['no_exception_handler'] !== 'cli')
	{
		error_log($headline.($e instanceof egw_exception_warning ? ': ' : ' ('.get_class($e).'): ').
			$e->getMessage().' ('.$e->getCode().')'.(!empty($e->details) ? ': '.$e->details : ''));
		error_log('File: '.str_replace(EGW_SERVER_ROOT, '', $e->getFile()).', Line: '.$e->getLine());
		foreach($trace as $line)
		{
			error_log($line);
		}
		error_log('# Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid'].
			', Request='.$_SERVER['REQUEST_METHOD'].' '.Api\Framework::getUrl($_SERVER['REQUEST_URI']).
			', User-agent='.$_SERVER['HTTP_USER_AGENT']);
	}
}

/**
 * Fail a little more gracefully then an uncaught exception
 *
 * Does NOT return
 *
 * @param Exception|Error $e
 */
function egw_exception_handler($e)
{
	// handle redirects without logging
	if ($e instanceof Api\Exception\Redirect)
	{
		Api\Egw::redirect($e->url, $e->app);
	}
	// logging all exceptions to the error_log (if not cli) and get headline
	$headline = null;
	_egw_log_exception($e,$headline);

	// exception handler for cli (command line interface) clients, no html, no logging
	if(!isset($_SERVER['HTTP_HOST']) || $GLOBALS['egw_info']['flags']['no_exception_handler'] == 'cli')
	{
		echo ($headline ? $headline.': ' : '').$e->getMessage().' ('.$e->getCode().')'."\n";
		echo $e->getFile().' ('.$e->getLine().")\n";
		if ($GLOBALS['egw_info']['server']['exception_show_trace'])
		{
			echo $e->getTraceAsString()."\n";
		}
		exit($e->getCode() ? $e->getCode() : 9999);		// allways give a non-zero exit code
	}
	// regular GUI exception
	if (!isset($GLOBALS['egw_info']['flags']['no_exception_handler']))
	{
		header('HTTP/1.1 500 '.$headline);
		$message = '<h3>'.Api\Html::htmlspecialchars($headline)."</h3>\n".
			'<pre><b>'.Api\Html::htmlspecialchars($e->getMessage().' ('.$e->getCode().')')."</b>\n\n";

		echo str_replace(EGW_SERVER_ROOT.'/', '', $e->getFile()).' ('.$e->getLine().")\n";

		// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
		if ($GLOBALS['egw_info']['server']['exception_show_trace'])
		{
			$message .= Api\Html::htmlspecialchars($e->getTraceAsString());
		}
		$message .= "</pre>\n";
		if (is_a($e, 'EGroupware\Api\Db\Exception\Setup'))
		{
			$setup_dir = str_replace(array('home/index.php','index.php'),'setup/',$_SERVER['PHP_SELF']);
			$message .= '<a href="'.$setup_dir.'">Run setup to install or configure EGroupware.</a>';
		}
		elseif (is_object($GLOBALS['egw']) && isset($GLOBALS['egw']->session) && method_exists($GLOBALS['egw'],'link'))
		{
			$message .= '<p><a href="'.$GLOBALS['egw']->link('/index.php').'">'.try_lang('Click here to resume your EGroupware Session.').'</a></p>';
		}
		if (is_object($GLOBALS['egw']) && isset($GLOBALS['egw']->framework))
		{
			$GLOBALS['egw']->framework->render($message,$headline);
		}
		else
		{
			echo "<html>\n<head>\n<title>".Api\Html::htmlspecialchars($headline)."</title>\n</head>\n<body>\n$message\n</body>\n</html>\n";
		}
	}
	// exception handler sending message back to the client as basic auth message
	elseif($GLOBALS['egw_info']['flags']['no_exception_handler'] == 'basic_auth')
	{
		$error = str_replace(array("\r", "\n"), array('', ' | '), $e->getMessage().' ('.$e->getCode().')');
		// to long http header cause Nginx to reject the response with 502 upstream sent too big header while reading response header from upstream
		if (strlen($error) > 256) $error = substr($error, 0, 256).' ...';
		header('WWW-Authenticate: Basic realm="'.$headline.' '.$error.'"');
		header('HTTP/1.1 401 Unauthorized');
		header('X-WebDAV-Status: 401 Unauthorized', true);
	}
	exit;
}

if (!isset($GLOBALS['egw_info']['flags']['no_exception_handler']) || $GLOBALS['egw_info']['flags']['no_exception_handler'] !== true)
{
	set_exception_handler('egw_exception_handler');
}

/**
 * Fail a little more gracefully then a catchable fatal error, by throwing an exception
 *
 * @param int $errno  level of the error raised: E_* constants
 * @param string $errstr error message
 * @param string $errfile filename that the error was raised in
 * @param int $errline line number the error was raised at
 * @link http://www.php.net/manual/en/function.set-error-handler.php
 * @throws ErrorException
 */
function egw_error_handler ($errno, $errstr, $errfile, $errline)
{
	switch ($errno)
	{
		case E_RECOVERABLE_ERROR:
		case E_USER_ERROR:
			error_log(__METHOD__."($errno, '$errstr', '$errfile', $errline)");
			throw new ErrorException($errstr, $errno, 0, $errfile, $errline);

		case E_WARNING:
		case E_USER_WARNING:
			// skip message for warnings suppressed via @-error-control-operator (eg. @is_dir($path))
			// can be commented out to get suppressed warnings too!
			if ((error_reporting() & $errno) && PHP_VERSION < 8.0)
			{
				_egw_log_exception(new egw_exception_warning($errstr.' in '.$errfile.' on line '.$errline));
			}
			break;
	}
}

/**
 * Used internally to trace warnings
 */
class egw_exception_warning extends Exception {}

// install our error-handler only for catchable fatal errors and warnings
// following error types cannot be handled with a user defined function: E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING
set_error_handler('egw_error_handler', E_RECOVERABLE_ERROR|E_USER_ERROR|E_WARNING|E_USER_WARNING);