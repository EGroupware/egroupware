<?php
/**
 * EGroupware XSS protection and other security relevant functions
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
 * check $_REQUEST data for XSS, vars containing script tags are moved to $GLOBALS['egw_unset_vars']
 *
 * @internal
 * @param array &$var reference of array to check
 * @param string $name ='' name of the array
 * @param boolean $log = true Log the results of checking to the error log
 */
function _check_script_tag(&$var,$name='',$log=true)
{
	static $preg=null;
	//old: '/<\/?[^>]*\b(iframe|script|javascript|on(before)?(abort|blur|change|click|dblclick|error|focus|keydown|keypress|keyup|load|mousedown|mousemove|mouseout|mouseover|mouseup|reset|select|submit|unload))\b[^>]*>/i';
	if (!isset($preg)) $preg =
		// forbidden tags like iframe or script
		'/(<(\s*\/)?\s*(iframe|script|object|embed|math|meta)[^a-z0-9]|'.
		// on* attributes
		'<[^>]*on(before)?(abort|blur|change|click|dblclick|error|focus|keydown|keypress|keyup|load|mouse(out|enter|leave|over|move|up|wheel|down)'.
		'|cached|beforeunload|online|offline|open|message|close|animation(start|end|iteration)|transition(start|end|run)|reset'.
		'|beforeprint|afterprint|composition(start|update|end)|fullscreenchange|fullscreenerror|cut|copy|auxclick|contextmenu'.
		'|wheel|drag(start|end|enter|over|leave)|drop|loadstart|progress|timeout|loadendreset|select|submit|unload|resize'.
		'|propertychange|page(hide|show)|scroll|readystatechange|start|popstate|form|input)\s*=|'.
		// ="javascript:*" diverse javascript attribute value
		'<[^>]+(href|src|dynsrc|lowsrc|background|style|poster|action)\s*=\s*("|\')?[^"\']*javascript|'.
		// benavior:url and expression in style attribute
		'<[^>]+style\s*=\s*("|\')[^>]*(behavior\s*:\s*url|expression)\s*\()/i';
	if (is_array($var))
	{
		foreach($var as $key => $val)
		{
			if (is_array($val))
			{
				_check_script_tag($var[$key],$name.'['.$key.']');
			}
			elseif(strpos($val, '<') !== false)	// speedup: ignore everything without <
			{
				if (preg_match($preg,$val))
				{
					// special handling for $_POST[json_data], to decend into it's decoded content, fixing json direct might break json syntax
					if ($name == '_POST' && $key == 'json_data' && ($json_data = json_decode($val, true)))
					{
						_check_script_tag($json_data, $name.'[json_data]');
						$_REQUEST[$key] = $var[$key] = json_encode($json_data);
						continue;
					}
					//error_log(__FUNCTION__."(,$name) ${name}[$key] = ".$var[$key]);
					$GLOBALS['egw_unset_vars'][$name.'['.$key.']'] = $var[$key];
					// attempt to clean the thing
					$var[$key] = Api\Html\HtmLawed::purify($val);
					// check if we succeeded, if not drop the var anyway, keep the egw_unset_var in any case
					if (preg_match($preg, $var[$key]))
					{
						if($log)
						{
							error_log("*** _check_script_tag($name): unset({$name}[$key]) with value '$val'");
						}
						unset($var[$key]);
					}
					elseif($log)
					{
						error_log("*** _check_script_tag($name): HtmlLawed::purify({$name}[$key]) succeeded '$val' --> '{$var[$key]}'");
					}
				}
			}
		}
		// in case some stupid old code expects the array-pointer to be at the start of the array
		reset($var);
	}
}

foreach(array('_COOKIE','_GET','_POST','_REQUEST','HTTP_GET_VARS','HTTP_POST_VARS') as $n => $where)
{
	$pregs = array(
		'order' => '/^[a-zA-Z0-9_,]*$/',
		'sort'  => '/^(ASC|DESC|asc|desc|0|1|2|3|4|5|6|7){0,1}$/',
	);
	foreach(array('order','sort') as $name)
	{
		if (isset($GLOBALS[$where][$name]) && !is_array($GLOBALS[$where][$name]) && !preg_match($pregs[$name],$GLOBALS[$where][$name]))
		{
			$GLOBALS[$where][$name] = '';
		}
	}
	// do the check for script-tags only for _GET and _POST or if we found something in _GET and _POST
	// speeds up the execution a bit
	if (isset($GLOBALS[$where]) && is_array($GLOBALS[$where]) && ($n < 3 || isset($GLOBALS['egw_unset_vars'])))
	{
		_check_script_tag($GLOBALS[$where],$where);
	}
}
//if (is_array($GLOBALS['egw_unset_vars'])) { echo "egw_unset_vars=<pre>".htmlspecialchars(print_r($GLOBALS['egw_unset_vars'],true))."</pre>"; exit; }

// $GLOBALS[egw_info][flags][currentapp] and die  if it contains something nasty or unexpected
if (isset($GLOBALS['egw_info']) && isset($GLOBALS['egw_info']['flags']) &&
	isset($GLOBALS['egw_info']['flags']['currentapp']) && !preg_match('/^[A-Za-z0-9_-]+$/',$GLOBALS['egw_info']['flags']['currentapp']))
{
	error_log(__FILE__.': '.__LINE__.' Invalid $GLOBALS[egw_info][flags][currentapp]='.array2string($GLOBALS['egw_info']['flags']['currentapp']).', $_SERVER[REQUEST_URI]='.array2string($_SERVER['REQUEST_URI']));
	die('Invalid $GLOBALS[egw_info][flags][currentapp]!');
}

// neutralises register_globals On, which is not used by eGW
// some code from the hardend php project: http://www.hardened-php.net/articles/PHPUG-PHP-Sicherheit-Parametermanipulationen.pdf
if (ini_get('register_globals'))
{
	function unregister_globals()
	{
		// protect against GLOBALS overwrite or setting egw_info
		if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS']) || isset($_REQUEST['egw_info']) || isset($_FILES['egw_info']))
		{
			die('GLOBALS overwrite detected!!!');
		}
		// unregister all globals
		$noUnset = array('GLOBALS','_GET','_POST','_COOKIE','_SERVER','_ENV','_FILES','xajax');
		foreach(array_unique(array_merge(
			array_keys($_GET),array_keys($_POST),array_keys($_COOKIE),array_keys($_SERVER),array_keys($_ENV),array_keys($_FILES),
			isset($_SESSION) && is_array($_SESSION) ? array_keys($_SESSION) : array())) as $k)
		{
			if (!in_array($k,$noUnset) && isset($GLOBALS[$k]))
			{
				unset($GLOBALS[$k]);
			}
		}
	}
	unregister_globals();
}

/**
 * Unserialize a php serialized string, but only if it contains NO objects 'O:\d:"' or 'C:\d:"' pattern
 *
 * Should be used for all external content, to guard against exploidts.
 *
 * PHP 7.0+ can be told not to instantiate any classes (and calling eg. it's destructor).
 * In fact it instantiates it as __PHP_Incomplete_Class without any methods and therefore disarming threads.
 *
 * @param string $str
 * @return mixed
 */
function php_safe_unserialize($str)
{
	if (PHP_VERSION >= 7)
	{
		return unserialize($str, array('allowed_classes' => false));
	}
	if ((strpos($str, 'O:') !== false || strpos($str, 'C:') !== false) &&
		preg_match('/(^|;|{)[OC]:\d+:"/', $str))
	{
		error_log(__METHOD__."('$str') contains objects --> return NULL");
		return null;	// null, not false, to not trigger behavior of returning string itself to app code
	}
	return unserialize($str);
}

/**
 * Unserialize a json or php serialized array
 *
 * Used to migrate from PHP serialized database values to json-encoded ones.
 *
 * @param string $str string with serialized array
 * @param boolean $allow_not_serialized =false true: return $str as is, if it is no serialized array
 * @return array|string|false false if content can not be unserialized (not null like json_decode!)
 */
function json_php_unserialize($str, $allow_not_serialized=false)
{
	if (!isset($str)) return $str;

	if ((in_array($str[0], array('a', 'i', 's', 'b', 'O', 'C')) && $str[1] == ':' || $str === 'N;') &&
		($arr = php_safe_unserialize($str)) !== false || $str === 'b:0;')
	{
		return $arr;
	}
	if (!$allow_not_serialized || $str[0] == '[' || $str[0] == '{' || $str[0] == '"' || $str === 'null' || ($val = json_decode($str, true)) !== null)
	{
		// json_decode return null, if it cant decode the content
		if (isset($val) || ($val = json_decode($str, true)) !== null || $str === 'null')
		{
			return $val;
		}
		return false;
	}
	return $str;
}