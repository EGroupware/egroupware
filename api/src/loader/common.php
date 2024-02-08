<?php
/**
 * EGroupware create enviroment:
 * - autoloader
 * - exception handler
 * - XSS and other security stuff
 * - global functions (incl. deprecated ones, if /phpgwapi dir is there)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

require_once dirname(__DIR__).'/autoload.php';

/**
 * To ease PHP 8.0 migration
 */
if (!function_exists('get_magic_quotes_gpc'))
{
	function get_magic_quotes_gpc()
	{
		return false;
	}
}

/**
* applies stripslashes recursively on each element of an array
*
* @param array &$var
* @return array
*/
function array_stripslashes($var)
{
	if (!is_array($var))
	{
		return stripslashes($var);
	}
	foreach($var as $key => $val)
	{
		$var[$key] = is_array($val) ? array_stripslashes($val) : stripslashes($val);
	}
	return $var;
}

/**
 * Return the number of bytes of a string, independent of mbstring.func_overload
 * AND the availability of mbstring
 *
 * @param string $str
 * @return int
 */
function bytes($str)
{
	static $func_overload = null;

	if (is_null($func_overload)) $func_overload = extension_loaded('mbstring') ? ini_get('mbstring.func_overload') : 0;

	return $func_overload & 2 ? mb_strlen($str,'ascii') : strlen($str);
}

/**
 * mbstring.func_overload safe substr
 *
 * @param string $data
 * @param int $offset
 * @param int $len
 * @return string
 */
function cut_bytes(&$data,$offset,$len=null)
{
	static $func_overload = null;

	if (is_null($func_overload)) $func_overload = extension_loaded('mbstring') ? ini_get('mbstring.func_overload') : 0;

	if (is_null($len))
	{
		return $func_overload & 2 ? mb_substr($data,$offset,bytes($data),'ascii') : substr($data,$offset);
	}
	return $func_overload & 2 ? mb_substr($data,$offset,$len,'ascii') : substr($data,$offset,$len);
}

if (!function_exists('imap_rfc822_parse_adrlist'))
{
	/**
	 * parses a (comma-separated) address string
	 *
	 * Examples:
	 * - Joe Doe <doe@example.com>
	 * - "Doe, Joe" <doe@example.com>
	 * - "\'Joe Doe\'" <doe@example.com>	// actually not necessary to quote
	 * - postmaster@example.com
	 * - root
	 * - "Joe on its way Down Under :-\)" <doe@example.com>
	 * - "Giant; \"Big\" Box" <sysservices@example.net>
	 * - sysservices@example.net <sysservices@example.net>	// this is wrong, because @ need to be quoted
	 *
	 * Invalid addresses, if detected, set host to '.SYNTAX-ERROR.'
	 *
	 * @param string $address - A string containing addresses
	 * @param string $default_host - The default host name
	 * @return array of objects. The objects properties are:
	 *		mailbox - the mailbox name (username)
	 *		host - the host name
	 *		personal - the personal name
	 *		adl - at domain source route
	 */
	function imap_rfc822_parse_adrlist($address, $default_host)
	{
		$addresses = array();
		$pending = '';
		foreach(explode(',', $address) as $part)
		{
			$trimmed = trim(($pending ? $pending.',' : '').$part);
			if ((($trimmed[0]??null) == '"' && substr($trimmed, -1) != '>')||strpos($part, '@')===false)
			{
				$pending .= ($pending ? $pending.',' : '').$part;
				continue;
			}
			$pending = '';
			$matches = $personal = $mailbox = $host = null;
			if (preg_match('/^(.*)<([^>@]+)(@([^>]+))?>$/', $trimmed, $matches))
			{
				$personal = trim($matches[1]);
				$mailbox = $matches[2];
				$host = $matches[4];
			}
			elseif (strpos($trimmed, '@') !== false)
			{
				list($mailbox, $host) = explode('@', $trimmed);
			}
			else
			{
				$mailbox = $trimmed;
			}
			if ($personal[0] == '"' && substr($personal, -1) == '"')
			{
				$personal = str_replace('\\', '', substr($personal, 1, -1));
			}
			if (empty($host)) $host = $default_host;

			$addresses[] = (object)array_diff(array(
				'mailbox'  => $mailbox,
				'host'     => $host,
				'personal' => $personal,
			), array(null, ''));
		}
		return $addresses;
	}
}

if (!function_exists('imap_rfc822_write_address'))
{
	/**
	 * Returns a properly formatted email address given the mailbox, host, and personal info
	 * @param string $mailbox - The mailbox name, see imap_open() for more information
	 * @param string $host - The email host part
	 * @param string $personal - The name of the account owner
	 * @return string properly formatted email address as defined in » RFC2822.
	 */
	function imap_rfc822_write_address($mailbox, $host, $personal)
	{
		if (is_array($personal))  $personal = implode(' ', $personal);

		//if (!preg_match('/^[!#$%&\'*+/0-9=?A-Z^_`a-z{|}~-]+$/u', $personal))	// that's how I read the rfc(2)822
		if ($personal && !preg_match('/^[0-9A-Z -]*$/iu', $personal))	// but quoting is never wrong, so quote more then necessary
		{
			$personal = '"'.str_replace(array('\\', '"'),array('\\\\', '\\"'), $personal).'"';
		}
		return ($personal ? $personal.' <' : '').$mailbox.($host ? '@'.$host : '').($personal ? '>' : '');
	}
}

if (!function_exists('imap_mime_header_decode'))
{
	/**
	 * Decodes MIME message header extensions that are non ASCII text (RFC2047)
	 *
	 * Uses Horde_Mime::decode() and therefore always returns only a single array element!
	 *
	 * @param string $text
	 * @return array with single object with attribute text already in our internal encoding and charset
	 * @deprecated use Horde_Mime::decode()
	 */
	function imap_mime_header_decode($text)
	{
		return array((object)array(
			'text' => Horde_Mime::decode($text),
			'charset' => Api\Translation::charset(),	// is already in our internal encoding!
		));
	}
}

if (!function_exists('mb_strlen'))
{
	/**
	 * Number of characters in a string
	 *
	 * @param string $str
	 * @return int
	 */
	function mb_strlen($str)
	{
		return strlen($str);
	}
}

if (!function_exists('mb_substr'))
{
	/**
	 * Return part of a string
	 *
	 * @param string $data
	 * @param int $offset
	 * @param int $len
	 * @return string
	 */
	function mb_substr(&$data, $offset, $len=null)
	{
		return is_null($len) ? substr($data, $offset) : substr($data, $offset, $len);
	}
}

/**
 * Format array or other types as (one-line) string, eg. for error_log statements
 *
 * @param mixed $var variable to dump
 * @return string
 */
function array2string($var)
{
	switch (($type = gettype($var)))
	{
		case 'boolean':
			return $var ? 'TRUE' : 'FALSE';
		case 'string':
			return "'$var'";
		case 'integer':
		case 'double':
		case 'resource':
			return $var;
		case 'NULL':
			return 'NULL';
		case 'object':
		case 'array':
			return str_replace(array("\n",'    '/*,'Array'*/),'',print_r($var,true));
	}
	return 'UNKNOWN TYPE!';
}

/**
 * Check if a given extension is loaded or load it if possible (requires sometimes disabled or unavailable dl function)
 *
 * @param string $extension
 * @param boolean $throw =false should we throw an exception, if $extension could not be loaded, default false = no
 * @return boolean true if loaded now, false otherwise
 * @throws Api\Exception\AssertionFailed
 */
function check_load_extension($extension,$throw=false)
{
	if (!defined('PHP_SHLIB_PREFIX'))
	{
		define('PHP_SHLIB_PREFIX',PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '');
	}
	// we check for the existens of 'dl', as multithreaded webservers dont have it and some hosters disable it !!!
	$loaded = extension_loaded($extension) || function_exists('dl') && @dl($dl=PHP_SHLIB_PREFIX.$extension.'.'.PHP_SHLIB_SUFFIX);

	if (!$loaded && $throw)
	{
		throw new Api\Exception\AssertionFailed("PHP extension '$extension' not loaded AND can NOT be loaded via dl('$dl')!");
	}
	return $loaded;
}

// include deprecated factory methods: CreateObject, ExecMethod, ...
require_once EGW_SERVER_ROOT.'/api/src/loader/deprecated_factory.php';

// include deprecated global functions, if phpgwapi is installed
if (file_exists(EGW_SERVER_ROOT.'/phpgwapi'))
{
	include_once EGW_SERVER_ROOT.'/phpgwapi/inc/deprecated_functions.inc.php';
}

/**
 * Return a properly formatted account_id.
 *
 * @author skeeter
 * This function will return a properly formatted account_id. This can take either a name or an account_id as paramters. If a name is provided it will return the associated id.
 * $account_id = get_account_id($accountid);
 * @param int|string $account_id either a name or an id
 * @param int|string $default_id either a name or an id
 * @return int account_id
 */
function get_account_id($account_id = '',$default_id = '')
{
	if (is_int($account_id))
	{
		return $account_id;
	}
	if ($account_id == '')
	{
		if ($default_id == '')
		{
			return $GLOBALS['egw_info']['user']['account_id'] ?? 0;
		}
		elseif (is_string($default_id))
		{
			return Api\Accounts::getInstance()->name2id($default_id);
		}
		return (int)$default_id;
	}
	elseif (is_string($account_id))
	{
		if((int)$account_id && Api\Accounts::getInstance()->exists((int)$account_id))
		{
			return (int)$account_id;
		}
		else
		{
			return Api\Accounts::getInstance()->name2id($account_id);
		}
	}
}

/**
 * print an array or object as pre-formatted html
 *
 * @param mixed $array
 * @param boolean $print =true print or return the content
 * @return string if !$print
 */
function _debug_array($array,$print=True)
{
	$output = '<pre>'.print_r($array,true)."</pre>\n";

	if ($print)
	{
		echo $output;
	}
	else
	{
		return $output;
	}
}

/**
 * backtrace of the calling functions for php4.3+ else menuaction/scriptname
 *
 * @author RalfBecker-AT-outdoor-training.de
 * @param int $remove =0 number of levels to remove
 * @return string function-names separated by slashes (beginning with the calling function not this one)
 */
function function_backtrace($remove=0)
{
	if (function_exists('debug_backtrace'))
	{
		$backtrace = debug_backtrace();
		//echo "function_backtrace($remove)<pre>".print_r($backtrace,True)."</pre>\n";
		foreach($backtrace as $n => $level)
		{
			if ($remove-- < 0)
			{
				$ret[] = (isset($level['class'])?$level['class'].$level['type']:'').$level['function'].
					($n > 0 && isset($backtrace[$n-1]['line']) ? ':'.$backtrace[$n-1]['line'] : '').	// add line number of call
					(empty($level['class']) && !is_object($level['args'][0]) && $level['function'] != 'unserialize' ?
					'('.substr(str_replace(EGW_SERVER_ROOT,'',(string)$level['args'][0]),0,64).')' : '');
			}
		}
		if (is_array($ret))
		{
			return implode(' / ',$ret);
		}
	}
	return $_GET['menuaction'] ? $_GET['menuaction'] : str_replace(EGW_SERVER_ROOT,'',$_SERVER['SCRIPT_FILENAME']);
}

if (!function_exists('lang') && !defined('NO_LANG'))	// setup declares an own version
{
	/**
	 * function to handle multilanguage support
	 *
	 * @param string $key message in englich with %1, %2, ... placeholders
	 * @param string $vars =null multiple values to replace the placeholders
	 * @return string translated message with placeholders replaced
	 */
	function lang($key,$vars=null)
	{
		if(!is_array($vars))
		{
			$vars = func_get_args();
			array_shift($vars);	// remove $key
		}
		return Api\Translation::translate($key,$vars);
	}
}

require_once __DIR__.'/security.php';
require_once __DIR__.'/exception.php';

/**
 * Public functions to be compatible with the exiting eGW framework
 */
if (!function_exists('parse_navbar'))
{
	/**
	 * echo's out the navbar
	 *
	 * @deprecated use $GLOBALS['egw']->framework->navbar() or $GLOBALS['egw']->framework::render()
	 */
	function parse_navbar()
	{
		echo $GLOBALS['egw']->framework->navbar();
	}
}

if (!function_exists('display_sidebox'))
{
	/**
	 * echo's out a sidebox menu
	 *
	 * @deprecated use $GLOBALS['egw']->framework->sidebox()
	 */
	function display_sidebox($appname,$menu_title,$_file)
	{
		$file = array_map(function($item)
		{
			return is_array($item) ? $item :
				str_replace('preferences.uisettings.index', 'preferences.preferences_settings.index', $item);
		}, $_file);
		$GLOBALS['egw']->framework->sidebox($appname,$menu_title,$file);
	}
}