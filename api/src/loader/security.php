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
 */
function _check_script_tag(&$var,$name='')
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
					error_log(__FUNCTION__."(,$name) ${name}[$key] = ".$var[$key]);
					$GLOBALS['egw_unset_vars'][$name.'['.$key.']'] = $var[$key];
					// attempt to clean the thing
					$var[$key] = $val = Api\Html\HtmLawed::purify($val);
					// check if we succeeded, if not drop the var anyway, keep the egw_unset_var in any case
					if (preg_match($preg,$val))
					{
						error_log("*** _check_script_tag($name): unset(${name}[$key]) with value $val***");
						unset($var[$key]);
					}
				}
			}
		}
		// in case some stupid old code expects the array-pointer to be at the start of the array
		reset($var);
	}
}

/* some _check_script_tag tests, should be commented out by default
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	if (!defined('EGW_INCLUDE_ROOT'))
	{
		define(EGW_INCLUDE_ROOT, realpath(dirname(__FILE__).'/../..'));
		define(EGW_API_INC, realpath(dirname(__FILE__)));
	}

	$total = $num_failed = 0;
	$patterns = array(
		// pattern => true: should fail, false: should not fail
		'< script >alert(1)< / script >' => true,
		'<span onMouseOver ="alert(1)">blah</span>' => true,
		'<a href=          "JaVascript: alert(1)">Click Me</a>' => true,
		// from https://www.acunetix.com/websitesecurity/cross-site-scripting/
		'<body onload=alert("XSS")>' => true,
		'<body background="javascript:alert("XSS")">' => true,
		'<iframe src=”http://evil.com/xss.html”>' => true,
		'<input type="image" src="javascript:alert(\'XSS\');">' => true,
		'<link rel="stylesheet" href="javascript:alert(\'XSS\');">' => true,
		'<table background="javascript:alert(\'XSS\')">' => true,
		'<td background="javascript:alert(\'XSS\')">' => true,
		'<div style="background-image: url(javascript:alert(\'XSS\'))">' => true,
		'<div style="width: expression(alert(\'XSS\'));">' => true,
		'<object type="text/x-scriptlet" data="http://hacker.com/xss.html">' => true,
		// false positiv tests
		'If 1 < 2, what does that mean for description, if 2 > 1.' => false,
		'If 1 < 2, what does that mean for a script, if 2 > 1.' => false,
		'<div>Script and Javascript: not evil ;-)' => false,
		'<span>style=background-color' => false,
		'<font face="Script MT Bold" size="4"><span style="font-size:16pt;">Hugo Sonstwas</span></font>' => false,
		'<mathias@stylite.de>' => false,
	);
	foreach($patterns as $pattern => $should_fail)
	{
		$test = array($pattern);
		unset($GLOBALS['egw_unset_vars']);
		_check_script_tag($test,'test');
		$failed = isset($GLOBALS['egw_unset_vars']) !== $should_fail;
		++$total;
		if ($failed) $num_failed++;
		echo "<p style='color: ".($failed?'red':'black')."'> ".Api\Html::htmlspecialchars($pattern).' '.
			(isset($GLOBALS['egw_unset_vars'])?'removed':'passed')."</p>";
	}
	$x = 1;
	// urls with attack vectors
	$urls = array(
		// we currently fail 76 of 666 test, thought they seem not to apply to our use case, as we check request data
		'https://gist.github.com/JohannesHoppe/5612274' => file(
			'https://gist.githubusercontent.com/JohannesHoppe/5612274/raw/60016bccbfe894dcd61a6be658a4469e403527de/666_lines_of_XSS_vectors.html'),
		// we currently fail 44 of 140 tests, thought they seem not to apply to our use case, as we check request data
		'https://html5sec.org/' => call_user_func(function() {
			$payloads = $items = null;
			if (!($items_js = file_get_contents('https://html5sec.org/items.js')) ||
				!preg_match_all("|^\s+'data'\s+:\s+'(.*)',$|m", $items_js, $items, PREG_PATTERN_ORDER) ||
				!($payload_js = file_get_contents('https://html5sec.org/payloads.js')) ||
				!preg_match_all("|^\s+'([^']+)'\s+:\s+'(.*)',$|m", $payload_js, $payloads, PREG_PATTERN_ORDER))
			{
				return false;
			}
			$replace = array(
				"\\'" => "'",
				'\\\\'=> '\\,',
				'\r'  => "\r",
				'\n'  => "\n",
			);
			foreach($payloads[1] as $n => $from) {
				$replace['%'.$from.'%'] = $payloads[2][$n];
			}
			return array_map(function($item) use ($replace) {
				return strtr($item, $replace);
			}, $items[1]);
		}),
	);
	foreach($urls as $url => $vectors)
	{
		// no all xss attack vectors from http://ha.ckers.org/xssAttacks.xml are relevant here! (needs interpretation)
		if (!$vectors)
		{
			echo "<p style='color:red'>Could NOT download or parse $url with attack vectors!</p>\n";
			continue;
		}
		echo "<p><b>Attacks from <a href='$url' target='_blank'>$url</a> with ".count($vectors)." tests:</b></p>";
		foreach($vectors as $line => $pattern)
		{
			$test = array($pattern);
			unset($GLOBALS['egw_unset_vars']);
			_check_script_tag($test, 'line '.(1+$line));
			$failed = !isset($GLOBALS['egw_unset_vars']);
			++$total;
			if ($failed) $num_failed++;
			echo "<p style='color: ".($failed?'red':'black')."'>".(1+$line).": ".Api\Html::htmlspecialchars($pattern).' '.
				(isset($GLOBALS['egw_unset_vars'])?'removed':'passed')."</p>";
		}
	}
	die("<p style='color: ".($num_failed?'red':'black')."'>Tests finished: $num_failed / $total failed</p>");
}*/

foreach(array('_GET','_POST','_REQUEST','HTTP_GET_VARS','HTTP_POST_VARS') as $n => $where)
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
	// speeds up the execusion a bit
	if (isset($GLOBALS[$where]) && is_array($GLOBALS[$where]) && ($n < 2 || isset($GLOBALS['egw_unset_vars'])))
	{
		_check_script_tag($GLOBALS[$where],$where);
	}
}
//if (is_array($GLOBALS['egw_unset_vars'])) { echo "egw_unset_vars=<pre>".htmlspecialchars(print_r($GLOBALS['egw_unset_vars'],true))."</pre>"; exit; }

// $GLOBALS[egw_info][flags][currentapp] and die  if it contains something nasty or unexpected
if (isset($GLOBALS['egw_info']) && isset($GLOBALS['egw_info']['flags']) &&
	isset($GLOBALS['egw_info']['flags']['currentapp']) && !preg_match('/^[A-Za-z0-9_-]+$/',$GLOBALS['egw_info']['flags']['currentapp']))
{
	error_log(__FILE__.': '.__LINE__.' Invalid $GLOBALS[egw_info][flags][currentapp]='.array2string($GLOBALS['egw_info']['flags']['currentapp']).', $_SERVER[REQUEST_URI]='.array2string($_SERVER[REQUEST_URI]));
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
 * PHP 7.0+ can be told not to instanciate any classes (and calling eg. it's destructor).
 * In fact it instanciates it as __PHP_Incomplete_Class without any methods and therefore disarming threads.
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

/* some test for object safe unserialisation
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	if (php_sapi_name() !== 'cli') echo "<pre>\n";
	foreach(array(
		// things unsafe to unserialize
		"O:34:\"Horde_Kolab_Server_Decorator_Clean\":2:{s:43:\"\x00Horde_Kolab_Server_Decorator_Clean\x00_server\";" => false,
		"O:20:\"Horde_Prefs_Identity\":2:{s:9:\"\x00*\x00_prefs\";O:11:\"Horde_Prefs\":2:{s:8:\"\x00*\x00_opts\";a:1:{s:12:\"sizecallback\";" => false,
		"a:2:{i:0;O:12:\"Horde_Config\":1:{s:13:\"\x00*\x00_oldConfig\";s:#{php_injection.length}:\"#{php_injection}\";}i:1;s:13:\"readXMLConfig\";}}" => false,
		'a:6:{i:0;i:0;i:1;d:2;i:2;s:4:"ABCD";i:3;r:3;i:4;O:8:"my_Class":2:{s:1:"a";r:6;s:1:"b";N;};i:5;C:16:"SplObjectStorage":14:{x:i:0;m:a:0:{}}' => false,
		serialize(new stdClass()) => false,
		serialize(array(new stdClass(), new SplObjectStorage())) => false,
		// string content, safe to unserialize
		serialize('O:8:"stdClass"') => true,
		serialize('C:16:"SplObjectStorage"') => true,
		serialize(array('a' => 'O:8:"stdClass"', 'b' => 'C:16:"SplObjectStorage"')) => true,
		// false positive: failing our php<7 regular expression, because it has correct delimiter (^|;|{) in front of pattern :-(
		serialize('O:8:"stdClass";C:16:"SplObjectStorage"') => true,
	) as $str => $result)
	{
		if ((bool)($r=php_safe_unserialize($str)) !== $result)
		{
			if (!$result)
			{
				if (PHP_VERSION >= 7)
				{
					if (preg_match_all('/([^ ]+) Object\(/', array2string($r), $matches))
					{
						foreach($matches[1] as $class)
						{
							if (!preg_match('/^__PHP_Incomplete_Class(#\d+)?$/', $class))
							{
								echo "FAILED: $str\n";
								continue 2;
							}
						}
					}
					echo "passed: ".array2string($str)." = ".array2string($r)."\n";
				}
				else
				{
					echo "FAILED: $str\n";
				}
			}
			else
			{
				echo "false positive: $str\n";
			}
		}
		else
		{
			echo "passed: $str\n";
		}
		//echo "result=".array2string($result).", php_save_unserialize('".htmlspecialchars($str)."') = ".array2string(php_safe_unserialize($str))." --> ".array2string((bool)php_safe_unserialize($str))."\n";
	}
}*/

/**
 * Unserialize a json or php serialized array
 *
 * Used to migrate from PHP serialized database values to json-encoded ones.
 *
 * @param string $str string with serialized array
 * @param boolean $allow_not_serialized =false true: return $str as is, if it is no serialized array
 * @return array|str|false false if content can not be unserialized (not null like json_decode!)
 */
function json_php_unserialize($str, $allow_not_serialized=false)
{
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
