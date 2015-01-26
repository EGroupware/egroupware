<?php
/**
 * EGroupware commonly used functions
 *
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 *
 * All newer parts (XSS checks, autoloading, exception handler, ...) are written by Ralf Becker.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
* applies stripslashes recursivly on each element of an array
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
			if (($trimmed[0] == '"' && substr($trimmed, -1) != '>')||strpos($part, '@')===false)
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
			'charset' => translation::charset(),	// is already in our internal encoding!
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
		throw new Exception ("PHP extension '$extension' not loaded AND can NOT be loaded via dl('$dl')!");
	}
	return $loaded;
}

/**
 * @internal Not to be used directly. Should only be used by print_debug()
 */
function print_debug_subarray($array)
{
	foreach($array as $key => $value)
	{
		if (is_array($value))
		{
			$vartypes[$key] = print_debug_subarray($value);
		}
		else
		{
			$vartypes[$key] = gettype($value);
		}
	}
	return $vartypes;
}

/**
 * print debug data only when debugging mode is turned on.
 *
 * @author seek3r
 * This function is used to debugging data.
 * print_debug('this is some debugging data',$somevar);
 */
function print_debug($message,$var = 'messageonly',$part = 'app', $level = 3)
{
	if (($part == 'app' && DEBUG_APP == True) || ($part == 'api' && DEBUG_API == True))
	{
		if (!defined('DEBUG_OUTPUT'))
		{
			define('DEBUG_OUTPUT', 1);
		}
		if ($level >= DEBUG_LEVEL)
		{
			if (!is_array($var))
			{
				if ($var != 'messageonly')
				{
					if (!DEBUG_DATATYPES)
					{
						$output = "$message\n$var";
					}
					else
					{
						$output = "$message\n$var is a ".gettype($var);
					}
				}
				else
				{
					$output = $message;
				}

				/* Bit 1 means to output to screen */
				if (!!(DEBUG_OUTPUT & 1))
				{
					echo "$output<br>\n";
				}
				/* Bit 2 means to output to sql */
				if (!!(DEBUG_OUTPUT & 2))
				{
					/* Need to flesh this out still. I dont have a table to dump this in yet.*/
					/* So the SQL statement will go here*/
				}

				/* Example of how this can be extended to output to other locations as well. This example uses a COM object */
				/*
				if (!!(DEBUG_OUTPUT & 32))
				{
					$obj_debug = new COM('Some_COM_App.Class','localhost');
					if (is_object($obj_debug))
					{
						$DebugMessage_return = $obj_debug->DebugMessage($output);
					}
				}
				*/
			}
			else
			{
				if (floor(phpversion()) > 3 && !!(DEBUG_OUTPUT & 2))
				{
					ob_start();
				}
				echo "<pre>\n$message\n";
				print_r($var);
				if (DEBUG_DATATYPES)
				{
					foreach($var as $key => $value)
					{
						if (is_array($value))
						{
							$vartypes[$key] = print_debug_subarray($value);
						}
						else
						{
							$vartypes[$key] = gettype($value);
						}
					}
					echo "Data Types:\n";
					print_r($vartypes);
				}
				echo "\n<pre>\n";
				if (floor(phpversion()) > 3 && !!(DEBUG_OUTPUT & 2))
				{
					$output .= ob_get_contents();
					ob_end_clean();
					/* Need to flesh this out still. I dont have a table to dump this in yet.*/
					/* So the SQL statement will go here*/
					if (!!(DEBUG_OUTPUT & 1))
					{
						echo "$output<br>\n";
					}
				}
			}
		}
	}
}

/**
  * Allows for array and direct function params as well as sanatization.
  *
  * @author seek3r
  * This function is used to validate param data as well as offer flexible function usage.
  *
	function somefunc()
	{
		$expected_args[0] = Array('name'=>'fname','default'=>'joe', 'type'=>'string');
		$expected_args[1] = Array('name'=>'mname','default'=>'hick', 'type'=>'string');
		$expected_args[2] = Array('name'=>'lname','default'=>'bob', 'type'=>'string');
		$recieved_args = func_get_args();
		$args = safe_args($expected_args, $recieved_args,__LINE__,__FILE__);
		echo 'Full name: '.$args['fname'].' '.$args['fname'].' '.$args['lname'].'<br>';
		//default result would be:
		// Full name: joe hick bob<br>
	}

	Using this it is possible to use the function in any of the following ways
	somefunc('jack','city','brown');
	or
	somefunc(array('fname'=>'jack','mname'=>'city','lname'=>'brown'));
	or
	somefunc(array('lname'=>'brown','fname'=>'jack','mname'=>'city'));

	For the last one, when using named params in an array you dont have to follow any order
	All three would result in - Full name: jack city brown<br>

	When you use this method of handling params you can secure your functions as well offer
	flexibility needed for both normal use and web services use.
	If you have params that are required just set the default as ##REQUIRED##
	Users of your functions can also use ##DEFAULT## to use your default value for a param
	when using the standard format like this:
	somefunc('jack','##DEFAULT##','brown');
	This would result in - Full name: jack hick brown<br>
	Its using the default value for the second param.
	Of course if you have the second param as a required field it will fail to work.
 */
function safe_args($expected, $recieved, $line='??', $file='??')
{
	/* This array will contain all the required fields */
	$required = Array();

	/* This array will contain all types for sanatization checking */
	/* only used when an array is passed as the first arg          */
	$types = Array();

	/* start by looping thru the expected list and set params with */
	/* the default values                                          */
	$num = count($expected);
	for ($i = 0; $i < $num; $i++)
	{
		$args[$expected[$i]['name']] = $expected[$i]['default'];
		if ($expected[$i]['default'] === '##REQUIRED##')
		{
			$required[$expected[$i]['name']] = True;
		}
		$types[$expected[$i]['name']] = $expected[$i]['type'];
	}

	/* Make sure they passed at least one param */
	if(count($recieved) != 0)
	{
		/* if used as standard function we loop thru and set by position */
		if(!is_array($recieved[0]))
		{
		for ($i = 0; $i < $num; $i++)
			{
				if(isset($recieved[$i]) && $recieved[$i] !== '##DEFAULT##')
				{
					if(sanitize($recieved[$i],$expected[$i]['type']))
					{
						$args[$expected[$i]['name']] = $recieved[$i];
						unset($required[$expected[$i]['name']]);
					}
					else
					{
						echo 'Fatal Error: Invalid paramater type for '.$expected[$i]['name'].' on line '.$line.' of '.$file.'<br>';
						exit;
					}
				}
			}
		}
		/* if used as standard function we loop thru and set by position */
		else
		{
			for ($i = 0; $i < $num; $i++)
			{
				$types[$expected[$i]['name']] = $expected[$i]['type'];
			}
			while(list($key,$val) = each($recieved[0]))
			{
				if($val !== '##DEFAULT##')
				{
					if(sanitize($val,$types[$key]) == True)
					{
						$args[$key] = $val;
						unset($required[$key]);
					}
					else
					{
						echo 'Fatal Error: Invalid paramater type for '.$key.' on line '.$line.' of '.$file.'<br>';
						exit;
					}
				}
			}
		}
	}
	if(count($required) != 0)
	{
		while (list($key) = each($required))
		{
			echo 'Fatal Error: Missing required paramater '.$key.' on line '.$line.' of '.$file.'<br>';
		}
		exit;
	}
	return $args;
}

/**
 * Validate data.
 *
 * @author seek3r
 * This function is used to validate input data.
 * sanitize('number',$somestring);
 */
function sanitize($string,$type)
{
	switch ($type)
	{
		case 'bool':
			if ($string == 1 || $string == 0)
			{
				return True;
			}
			break;

		case 'isprint':
			$length = strlen($string);
			$position = 0;
			while ($length > $position)
			{
				$char = substr($string, $position, 1);
				if ($char < ' ' || $char > '~')
				{
					return False;
				}
				$position = $position + 1;
			}
			return True;

		case 'alpha':
			if (preg_match("/^[a-z]+$/i", $string))
			{
				return True;
			}
			break;

		case 'number':
			if (preg_match("/^[0-9]+$/i", $string))
			{
				return True;
			}
			break;

		case 'alphanumeric':
			if (preg_match("/^[a-z0-9 -._]+$/i", $string))
			{
				return True;
			}
			break;
		case 'string':
			if (preg_match("/^[a-z]+$/i", $string))
			{
				return True;
			}
			break;
		case 'ip':
			if (preg_match('/'."^[0-9]{1,3}(\.[0-9]{1,3}){3}$".'/i',$string))
			{
				$octets = preg_split('/\./',$string);
				for ($i=0; $i != count($octets); $i++)
				{
					if ($octets[$i] < 0 || $octets[$i] > 255)
					{
						return False;
					}
				}
				return True;
			}
			return False;

		case 'file':
			if (preg_match("/^[a-z0-9_]+\.+[a-z]+$/i", $string))
			{
				return True;
			}
			break;
		case 'email':
			if (preg_match('/'."^([[:alnum:]_%+=.-]+)@([[:alnum:]_.-]+)\.([a-z]{2,3}|[0-9]{1,3})$".'/i',$string))
			{
				return True;
			}
			break;
		case 'password':
			$password_length = strlen($string);
			$password_numbers = Array('0','1','2','3','4','5','6','7','8','9');
			$password_special_chars = Array(' ','~','`','!','@','#','$','%','^','&','*','(',')','_','+','-','=','{','}','|','[',']',"\\",':','"',';',"'",'<','>','?',',','.','/');

			if(@isset($GLOBALS['egw_info']['server']['pass_min_length']) && is_int($GLOBALS['egw_info']['server']['pass_min_length']) && $GLOBALS['egw_info']['server']['pass_min_length'] > 1)
			{
				$min_length = $GLOBALS['egw_info']['server']['pass_min_length'];
			}
			else
			{
				$min_length = 1;
			}

			if(@isset($GLOBALS['egw_info']['server']['pass_require_non_alpha']) && $GLOBALS['egw_info']['server']['pass_require_non_alpha'] == True)
			{
				$pass_verify_non_alpha = False;
			}
			else
			{
				$pass_verify_non_alpha = True;
			}

			if(@isset($GLOBALS['egw_info']['server']['pass_require_numbers']) && $GLOBALS['egw_info']['server']['pass_require_numbers'] == True)
			{
				$pass_verify_num = False;
			}
			else
			{
				$pass_verify_num = True;
			}

			if(@isset($GLOBALS['egw_info']['server']['pass_require_special_char']) && $GLOBALS['egw_info']['server']['pass_require_special_char'] == True)
			{
				$pass_verify_special_char = False;
			}
			else
			{
				$pass_verify_special_char = True;
			}

			if ($password_length >= $min_length)
			{
				for ($i=0; $i != $password_length; $i++)
				{
					$cur_test_string = substr($string, $i, 1);
					if (in_array($cur_test_string, $password_numbers) || in_array($cur_test_string, $password_special_chars))
					{
						$pass_verify_non_alpha = True;
						if (in_array($cur_test_string, $password_numbers))
						{
							$pass_verify_num = True;
						}
						elseif (in_array($cur_test_string, $password_special_chars))
						{
							$pass_verify_special_char = True;
						}
					}
				}

				if ($pass_verify_num == False)
				{
					$GLOBALS['egw_info']['flags']['msgbox_data']['Password requires at least one non-alpha character']=False;
				}

				if ($pass_verify_num == False)
				{
					$GLOBALS['egw_info']['flags']['msgbox_data']['Password requires at least one numeric character']=False;
				}

				if ($pass_verify_special_char == False)
				{
					$GLOBALS['egw_info']['flags']['msgbox_data']['Password requires at least one special character (non-letter and non-number)']=False;
				}

				if ($pass_verify_num == True && $pass_verify_special_char == True)
				{
					return True;
				}
				return False;
			}
			$GLOBALS['egw_info']['flags']['msgbox_data']['Password must be at least '.$min_length.' characters']=False;
			return False;

		case 'any':
			return True;

		default :
			if (isset($GLOBALS['egw_info']['server']['sanitize_types'][$type]['type']))
			{
				if ($GLOBALS['egw_info']['server']['sanitize_types'][$type]['type']($GLOBALS['egw_info']['server']['sanitize_types'][$type]['string'], $string))
				{
					return True;
				}
			}
			return False;
	}
}

function reg_var($varname, $method='any', $valuetype='alphanumeric',$default_value='',$register=True)
{
	if($method == 'any' || $method == array('any'))
	{
		$method = Array('POST','GET','COOKIE','SERVER','FILES','GLOBAL','DEFAULT');
	}
	elseif(!is_array($method))
	{
		$method = Array($method);
	}
	$cnt = count($method);
	for($i=0;$i<$cnt;$i++)
	{
		switch(strtoupper($method[$i]))
		{
			case 'DEFAULT':
				if($default_value)
				{
					$value = $default_value;
					$i = $cnt+1; /* Found what we were looking for, now we end the loop */
				}
				break;
			case 'GLOBAL':
				if(@isset($GLOBALS[$varname]))
				{
					$value = $GLOBALS[$varname];
					$i = $cnt+1;
				}
				break;
			case 'POST':
			case 'GET':
			case 'COOKIE':
			case 'SERVER':
				if(phpversion() >= '4.1.0')
				{
					$meth = '_'.strtoupper($method[$i]);
				}
				else
				{
					$meth = 'HTTP_'.strtoupper($method[$i]).'_VARS';
				}
				if(@isset($GLOBALS[$meth][$varname]))
				{
					$value = $GLOBALS[$meth][$varname];
					$i = $cnt+1;
				}
				if(get_magic_quotes_gpc() && isset($value))
				{
					// we need to stripslash 3 levels of arrays
					// because of the password function in preferences
					// it's named ['user']['variablename']['pw']
					// or something like this in projects
					// $values['budgetBegin']['1']['year']
					if(@is_array($value))
					{
						/* stripslashes on the first level of array values */
						foreach($value as $name => $val)
						{
							if(@is_array($val))
							{
								foreach($val as $name2 => $val2)
								{
									if(@is_array($val2))
									{
										foreach($val2 as $name3 => $val3)
										{
											$value[$name][$name2][$name3] = stripslashes($val3);
										}
									}
									else
									{
										$value[$name][$name2] = stripslashes($val2);
									}
								}
							}
							else
							{
								$value[$name] = stripslashes($val);
							}
						}
					}
					else
					{
						/* stripslashes on this (string) */
						$value = stripslashes($value);
					}
				}
				break;
			case 'FILES':
				if(phpversion() >= '4.1.0')
				{
					$meth = '_FILES';
				}
				else
				{
					$meth = 'HTTP_POST_FILES';
				}
				if(@isset($GLOBALS[$meth][$varname]))
				{
					$value = $GLOBALS[$meth][$varname];
					$i = $cnt+1;
				}
				break;
			default:
				if(@isset($GLOBALS[strtoupper($method[$i])][$varname]))
				{
					$value = $GLOBALS[strtoupper($method[$i])][$varname];
					$i = $cnt+1;
				}
				break;
		}
	}

	if (@!isset($value))
	{
		$value = $default_value;
	}

	if (@!is_array($value))
	{
		if ($value == '')
		{
			$result = $value;
		}
		else
		{
			if (sanitize($value,$valuetype) == 1)
			{
				$result = $value;
			}
			else
			{
				$result = $default_value;
			}
		}
	}
	else
	{
		reset($value);
		while(list($k, $v) = each($value))
		{
			if ($v == '')
			{
				$result[$k] = $v;
			}
			else
			{
				if (is_array($valuetype))
				{
					$vt = $valuetype[$k];
				}
				else
				{
					$vt = $valuetype;
				}

				if (sanitize($v,$vt) == 1)
				{
					$result[$k] = $v;
				}
				else
				{
					if (is_array($default_value))
					{
						$result[$k] = $default_value[$k];
					}
					else
					{
						$result[$k] = $default_value;
					}
				}
			}
		}
	}
	if($register)
	{
		$GLOBALS['egw_info'][$GLOBALS['egw_info']['flags']['currentapp']][$varname] = $result;
	}
	return $result;
}

/**
 * retrieve a value from either a POST, GET, COOKIE, SERVER or from a class variable.
 *
 * @author skeeter
 * This function is used to retrieve a value from a user defined order of methods.
 * $this->id = get_var('id',array('HTTP_POST_VARS'||'POST','HTTP_GET_VARS'||'GET','HTTP_COOKIE_VARS'||'COOKIE','GLOBAL','DEFAULT'));
 * @param $variable name
 * @param $method ordered array of methods to search for supplied variable
 * @param $default_value (optional)
 */
function get_var($variable,$method='any',$default_value='')
{
	if(!@is_array($method))
	{
		$method = array($method);
	}
	return reg_var($variable,$method,'any',$default_value,False);
}

/**
 * Load a class and include the class file if not done so already.
 *
 * This function is used to create an instance of a class, and if the class file has not been included it will do so.
 * $GLOBALS['egw']->acl =& CreateObject('phpgwapi.acl');
 *
 * @author RalfBecker@outdoor-training.de
 * @param $classname name of class
 * @param $p1,$p2,... class parameters (all optional)
 * @return object reference to an object
 */
function &CreateObject($class)
{
	list($appname,$classname) = explode('.',$class);

	if (!class_exists($classname))
	{
		static $replace = array(
			'datetime'    => 'egw_datetime',
			'uitimesheet' => 'timesheet_ui',
			'uiinfolog'   => 'infolog_ui',
			'uiprojectmanager'  => 'projectmanager_ui',
			'uiprojectelements' => 'projectmanager_elements_ui',
			'uiroles'           => 'projectmanager_roles_ui',
			'uimilestones'      => 'projectmanager_milestones_ui',
			'uipricelist'       => 'projectmanager_pricelist_ui',
			'bowiki'            => 'wiki_bo',
			'uicategories'      => 'admin_categories',
			'defaultimap'		=> 'emailadmin_oldimap',
		);
		if (!file_exists(EGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php') || isset($replace[$classname]))
		{
			if (isset($replace[$classname]))
			{
				//throw new Exception(__METHOD__."('$class') old classname '$classname' used in menuaction=$_GET[menuaction]!");
				error_log(__METHOD__."('$class') old classname '$classname' used in menuaction=$_GET[menuaction]!");
				$classname = $replace[$classname];
			}
		}
		if (!file_exists($f=EGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php'))
		{
			throw new egw_exception_assertion_failed(__FUNCTION__."($classname) file $f not found!");
		}
		// this will stop php with a 500, if the class does not exist or there are errors in it (syntax error go into the error_log)
		require_once(EGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php');
	}
	$args = func_get_args();
	switch(count($args))
	{
		case 1:
			$obj = new $classname;
			break;
		case 2:
			$obj = new $classname($args[1]);
			break;
		case 3:
			$obj = new $classname($args[1],$args[2]);
			break;
		case 4:
			$obj = new $classname($args[1],$args[2],$args[3]);
			break;
		default:
			$code = '$obj = new ' . $classname . '(';
			foreach(array_keys($args) as $n)
			{
				if ($n)
				{
					$code .= ($n > 1 ? ',' : '') . '$args[' . $n . ']';
				}
			}
			$code .= ');';
			eval($code);
			break;
	}
	if (!is_object($obj))
	{
		echo "<p>CreateObject('$class'): Cant instanciate class!!!<br />\n".function_backtrace(1)."</p>\n";
	}
	return $obj;
}

/**
 * Execute a function with multiple arguments
 * We take object $GLOBALS[classname] from class if exists
 *
 * @param string app.class.method method to execute
 * @example ExecObject('etemplates.so_sql.search',$criteria,$key_only,...);
 * @return mixed reference to returnvalue of the method
 */
function &ExecMethod2($acm)
{
	// class::method is php5.2.3+
	if (strpos($acm,'::') !== false && version_compare(PHP_VERSION,'5.2.3','<'))
	{
		list($class,$method) = explode('::',$acm);
		$acm = array($class,$method);
	}
	if (!is_callable($acm))
	{
		list(,$class,$method) = explode('.',$acm);
		if (!is_object($obj =& $GLOBALS[$class]))
		{
			$obj =& CreateObject($acm);
		}

		if (!method_exists($obj,$method))
		{
			echo "<p><b>".function_backtrace()."</b>: no methode '$method' in class '$class'</p>\n";
			return False;
		}
		$acm = array($obj,$method);
	}
	$args = func_get_args();
	unset($args[0]);

	return call_user_func_array($acm,$args);
}

/**
 * Execute a function, and load a class and include the class file if not done so already.
 *
 * This function is used to create an instance of a class, and if the class file has not been included it will do so.
 *
 * @author seek3r
 * @param $method to execute
 * @param $functionparam function param should be an array
 * @param $loglevel developers choice of logging level
 * @param $classparams params to be sent to the contructor
 * @return mixed returnvalue of method
 */
function ExecMethod($method, $functionparam = '_UNDEF_', $loglevel = 3, $classparams = '_UNDEF_')
{
	unset($loglevel);	// not used
	/* Need to make sure this is working against a single dimensional object */
	$partscount = count(explode('.',$method)) - 1;

	if (!is_callable($method) && $partscount == 2)
	{
		list($appname,$classname,$functionname) = explode(".", $method);
		if (!is_object($GLOBALS[$classname]))
		{
			// please note: no reference assignment (=&) here, as $GLOBALS is a reference itself!!!
			if ($classparams != '_UNDEF_' && ($classparams || $classparams != 'True'))
			{
				$GLOBALS[$classname] = CreateObject($appname.'.'.$classname, $classparams);
			}
			else
			{
				$GLOBALS[$classname] = CreateObject($appname.'.'.$classname);
			}
		}

		if (!method_exists($GLOBALS[$classname],$functionname))
		{
			error_log("ExecMethod('$method', ...) No methode '$functionname' in class '$classname'! ".function_backtrace());
			return false;
		}
		$method = array($GLOBALS[$classname],$functionname);
	}
	if (is_callable($method))
	{
		return $functionparam != '_UNDEF_' ? call_user_func($method,$functionparam) : call_user_func($method);
	}
	error_log("ExecMethod('$method', ...) Error in parts! ".function_backtrace());
	return false;
}

/**
 * Return a properly formatted account_id.
 *
 * @author skeeter
 * This function will return a properly formatted account_id. This can take either a name or an account_id as paramters. If a name is provided it will return the associated id.
 * $account_id = get_account_id($accountid);
 * @param int/string $account_id either a name or an id
 * @param int/string $default_id either a name or an id
 * @return int account_id
 */
function get_account_id($account_id = '',$default_id = '')
{
	if (gettype($account_id) == 'integer')
	{
		return $account_id;
	}
	elseif ($account_id == '')
	{
		if ($default_id == '')
		{
			return (isset($GLOBALS['egw_info']['user']['account_id'])?$GLOBALS['egw_info']['user']['account_id']:0);
		}
		elseif (is_string($default_id))
		{
			return $GLOBALS['egw']->accounts->name2id($default_id);
		}
		return (int)$default_id;
	}
	elseif (is_string($account_id))
	{
		if($GLOBALS['egw']->accounts->exists((int)$account_id) == True)
		{
			return (int)$account_id;
		}
		else
		{
			return $GLOBALS['egw']->accounts->name2id($account_id);
		}
	}
}

/**
 * sets the file system seperator depending on OS
 *
 * This is completely unnecessary, as you can use forward slashes in php under every OS -- RalfBecker 2005/11/09
 *
 * @deprecated just use forward slashes supported by PHP on all OS
 * @return file system separator
 */
function filesystem_separator()
{
	if(PHP_OS == 'Windows' || PHP_OS == 'OS/2' || PHP_OS == 'WINNT')
	{
		return '\\';
	}
	else
	{
		return '/';
	}
}

/**
 * @deprecated just use forward slashes supported by PHP on all OS
 */
define('SEP', filesystem_separator());

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
 * eGW version checking, is eGW version in $a < $b
 *
 * @param string $a	egw version number to check if less than $b
 * @param string $b egw version number to check $a against
 * @return boolean True if $a < $b
 */
function alessthanb($a,$b,$DEBUG=False)
{
	$num = array('1st','2nd','3rd','4th');

	if ($DEBUG)
	{
		echo'<br>Input values: ' . 'A="'.$a.'", B="'.$b.'"';
	}
	$newa = str_replace('pre','.',$a);
	$newb = str_replace('pre','.',$b);
	$testa = explode('.',$newa);
	if(@$testa[1] == '')
	{
		$testa[1] = 0;
	}
	if(@$testa[3] == '')
	{
		$testa[3] = 0;
	}
	$testb = explode('.',$newb);
	if(@$testb[1] == '')
	{
		$testb[1] = 0;
	}
	if(@$testb[3] == '')
	{
		$testb[3] = 0;
	}
	$less = 0;

	for ($i=0;$i<count($testa);$i++)
	{
		if ($DEBUG) { echo'<br>Checking if '. (int)$testa[$i] . ' is less than ' . (int)$testb[$i] . ' ...'; }
		if ((int)$testa[$i] < (int)$testb[$i])
		{
			if ($DEBUG) { echo ' yes.'; }
			$less++;
			if ($i<3)
			{
				/* Ensure that this is definitely smaller */
				if ($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely less than B."; }
				$less = 5;
				break;
			}
		}
		elseif((int)$testa[$i] > (int)$testb[$i])
		{
			if ($DEBUG) { echo ' no.'; }
			$less--;
			if ($i<2)
			{
				/* Ensure that this is definitely greater */
				if ($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely greater than B."; }
				$less = -5;
				break;
			}
		}
		else
		{
			if ($DEBUG) { echo ' no, they are equal.'; }
			$less = 0;
		}
	}
	if ($DEBUG) { echo '<br>Check value is: "'.$less.'"'; }
	if ($less>0)
	{
		if ($DEBUG) { echo '<br>A is less than B'; }
		return True;
	}
	elseif($less<0)
	{
		if ($DEBUG) { echo '<br>A is greater than B'; }
		return False;
	}
	else
	{
		if ($DEBUG) { echo '<br>A is equal to B'; }
		return False;
	}
}

/**
 * eGW version checking, is eGW version in $a > $b
 *
 * @param string $a eGW version number to check if more than $b
 * @param string $b eGW version number to check check $a against
 * @return boolean True if $a > $b
 */
function amorethanb($a,$b,$DEBUG=False)
{
	$num = array('1st','2nd','3rd','4th');

	if ($DEBUG)
	{
		echo'<br>Input values: ' . 'A="'.$a.'", B="'.$b.'"';
	}
	$newa = str_replace('pre','.',$a);
	$newb = str_replace('pre','.',$b);
	$testa = explode('.',$newa);
	if($testa[3] == '')
	{
		$testa[3] = 0;
	}
	$testb = explode('.',$newb);
	if($testb[3] == '')
	{
		$testb[3] = 0;
	}
	$less = 0;

	for ($i=0;$i<count($testa);$i++)
	{
		if ($DEBUG) { echo'<br>Checking if '. (int)$testa[$i] . ' is more than ' . (int)$testb[$i] . ' ...'; }
		if ((int)$testa[$i] > (int)$testb[$i])
		{
			if ($DEBUG) { echo ' yes.'; }
			$less++;
			if ($i<3)
			{
				/* Ensure that this is definitely greater */
				if ($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely greater than B."; }
				$less = 5;
				break;
			}
		}
		elseif((int)$testa[$i] < (int)$testb[$i])
		{
			if ($DEBUG) { echo ' no.'; }
			$less--;
			if ($i<2)
			{
				/* Ensure that this is definitely smaller */
				if ($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely less than B."; }
				$less = -5;
				break;
			}
		}
		else
		{
			if ($DEBUG) { echo ' no, they are equal.'; }
			$less = 0;
		}
	}
	if ($DEBUG) { echo '<br>Check value is: "'.$less.'"'; }
	if ($less>0)
	{
		if ($DEBUG) { echo '<br>A is greater than B'; }
		return True;
	}
	elseif($less<0)
	{
		if ($DEBUG) { echo '<br>A is less than B'; }
		return False;
	}
	else
	{
		if ($DEBUG) { echo '<br>A is equal to B'; }
		return False;
	}
}

/**
 * prepend a prefix to an array of table names
 *
 * @author Adam Hull (aka fixe) - No copyright claim
 * @param	$prefix	the string to be prepended
 * @param	$tables	and array of tables to have the prefix prepended to
 * @return array of table names with the prefix prepended
 */
function prepend_tables_prefix($prefix,$tables)
{
	foreach($tables as $key => $value)
	{
		$tables[$key] = $prefix.$value;
	}
	return $tables;
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
					(!$level['class'] && !is_object($level['args'][0]) && $level['function'] != 'unserialize' ?
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

/**
 * check $_REQUEST data for XSS, vars containing script tags are moved to $GLOBALS['egw_unset_vars']
 *
 * @internal
 * @param array &$var reference of array to check
 * @param string $name ='' name of the array
 */
function _check_script_tag(&$var,$name='')
{
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
				static $preg = '/<\/?[^>]*\b(iframe|script|javascript|on(before)?(abort|blur|change|click|dblclick|error|focus|keydown|keypress|keyup|load|mousedown|mousemove|mouseout|mouseover|mouseup|reset|select|submit|unload))\b[^>]*>/i';
				if (preg_match($preg,$val))
				{
					error_log(__FUNCTION__."(,$name) ${name}[$key] = ".$var[$key]);
					$GLOBALS['egw_unset_vars'][$name.'['.$key.']'] = $var[$key];
					// attempt to clean the thing
					$var[$key] = $val = html::purify($val);
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
		'<script>alert(1)</script>' => true,
		'<span onmouseover="alert(1)">blah</span>' => true,
		'<a href="javascript:alert(1)">Click Me</a>' => true,
		'If 1 < 2, what does that mean for description, if 2 > 1.' => false,
		'If 1 < 2, what does that mean for a script, if 2 > 1.' => false,	// false positive
	);
	foreach($patterns as $pattern => $should_fail)
	{
		$test = array($pattern);
		unset($GLOBALS['egw_unset_vars']);
		_check_script_tag($test,'test');
		$failed = isset($GLOBALS['egw_unset_vars']) !== $should_fail;
		++$total;
		if ($failed) $num_failed++;
		echo "<p style='color: ".($failed?'red':'black')."'> ".html::htmlspecialchars($pattern).' '.
			(isset($GLOBALS['egw_unset_vars'])?'removed':'passed')."</p>";
	}
	// no all xss attack vectors from http://ha.ckers.org/xssAttacks.xml are relevant here! (needs interpretation)
	$vectors = new SimpleXMLElement(file_get_contents('http://ha.ckers.org/xssAttacks.xml'));
	foreach($vectors->attack as $attack)
	{
		$test = array((string)$attack->code);
		unset($GLOBALS['egw_unset_vars']);
		_check_script_tag($test, $attack->name);
		$failed = !isset($GLOBALS['egw_unset_vars']);
		++$total;
		if ($failed) $num_failed++;
		echo "<p><span style='color: ".($failed?'red':'black')."'>".html::htmlspecialchars($attack->name).": ".html::htmlspecialchars($attack->code)." ".
			(isset($GLOBALS['egw_unset_vars'])?'removed':'passed').
			"</span><br /><span style='color: gray'>".html::htmlspecialchars($attack->desc)."</span></p>";
	}
	die("<p>Tests finished: $num_failed / $total failed</p>");
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

if (!function_exists('lang') || defined('NO_LANG'))	// setup declares an own version
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
		return translation::translate($key,$vars);
	}
}

/**
 * Translate message only if translation object is already loaded
 *
 * This function is usefull for exception handlers or early stages of the initialisation of the egw object,
 * as calling lang would try to load the translations, evtl. cause more errors, eg. because there's no db-connection.
 *
 * @param string $key message in englich with %1, %2, ... placeholders
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
	return class_exists(translations,false) ? translation::translate($key,$vars) : str_replace($varnames,$vars,$key);
}

/**
 * Unserialize a php serialized string, but only if it contains NO objects 'O:\d:"' or 'C:\d:"' pattern
 *
 * Should be used for all external content, to guard against exploidts.
 *
 * @param string $str
 * @return mixed
 */
function php_safe_unserialize($str)
{
	if ((strpos($str, 'O:') !== false || strpos($str, 'C:') !== false) &&
		preg_match('/(^|;|{)[OC]:\d+:"/', $str))
	{
		error_log(__METHOD__."('$str') contains objects --> return false");
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
		// string content, safe to unserialize
		serialize('O:8:"stdClass"') => true,
		serialize('C:16:"SplObjectStorage"') => true,
		serialize(array('a' => 'O:8:"stdClass"', 'b' => 'C:16:"SplObjectStorage"')) => true,
		// false positive: failing our tests, because it has correct delimiter (^|;|{) in front of pattern :-(
		serialize('O:8:"stdClass";C:16:"SplObjectStorage"') => true,
	) as $str => $result)
	{
		if ((bool)php_safe_unserialize($str) !== $result)
		{
			if (!$result)
			{
				echo "FAILED: $str\n";
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
	}
}*/

/**
 * Unserialize a json or php serialized array
 *
 * Used to migrate from PHP serialized database values to json-encoded ones.
 *
 * @param string $str string with serialized array
 * @param boolean $allow_not_serialized =false true: return $str as is, if it is no serialized array
 * @return array|str|false
 */
function json_php_unserialize($str, $allow_not_serialized=false)
{
	if ((in_array($str[0], array('a', 'i', 's', 'b', 'O', 'C')) && $str[1] == ':' || $str === 'N;') &&
		($arr = php_safe_unserialize($str)) !== false || $str === 'b:0;')
	{
		return $arr;
	}
	if (!$allow_not_serialized || $str[0] == '[' || $str[0] == '{' || $str[0] == '"' || $str === 'null' || ($val = json_decode($str)) !== null)
	{
		return isset($val) ? $val : json_decode($str, true);
	}
	return $str;
}

/**
 * New PSR-4 autoloader for EGroupware
 *
 * class_exists('\\EGroupware\\Api\\Vfs');                           // /api/src/Vfs.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Dav\\Directory');           // /api/src/Vfs/Dav/Directory.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Sqlfs\\StreamWrapper');     // /api/src/Vfs/Sqlfs/StreamWrapper.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Sqlfs\\Utils');             // /api/src/Vfs/Sqlfs/Utils.php
 * class_exists('\\EGroupware\\Stylite\\Versioning\\StreamWrapper'); // /stylite/src/Versioning/StreamWrapper.php
 * class_exists('\\EGroupware\\Calendar\\Ui');                       // /calendar/src/Ui.php
 * class_exists('\\EGroupware\\Calendar\\Ui\\Lists');                // /calendar/src/Ui/Lists.php
 * class_exists('\\EGroupware\\Calendar\\Ui\\Views');                // /calendar/src/Ui/Views.php
 */
spl_autoload_register(function($class)
{
	$parts = explode('\\', $class);
	if (array_shift($parts) != 'EGroupware') return;	// not our prefix

	$app = lcfirst(array_shift($parts));
	$base = EGW_INCLUDE_ROOT.'/'.$app.'/src/';
	$path = $base.implode('/', $parts).'.php';

	if (file_exists($path))
	{
		require_once $path;
		//error_log("PSR4_autoload('$class') --> require_once($path) --> class_exists('$class')=".array2string(class_exists($class,false)));
	}
});

/**
 * Old autoloader for EGroupware understanding the following naming schema:
 *
 *	1. new (prefered) nameing schema: app_class_something loading app/inc/class.class_something.inc.php
 *	2. API classes: classname loading phpgwapi/inc/class.classname.inc.php
 *  2a.API classes containing multiple classes per file eg. egw_exception* in class.egw_exception.inc.php
 *	3. eTemplate classes: classname loading etemplate/inc/class.classname.inc.php
 *
 * @param string $class name of class to load
 */
spl_autoload_register(function($class)
{
	// fixing warnings generated by php 5.3.8 is_a($obj) trying to autoload huge strings
	if (strlen($class) > 64 || strpos($class, '.') !== false) return;

	$components = explode('_',$class);
	$app = array_shift($components);
	// classes using the new naming schema app_class_name, eg. admin_cmd
	if (file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'/inc/class.'.$class.'.inc.php') ||
		// classes using the new naming schema app_class_name, eg. admin_cmd
		isset($components[0]) && file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'/inc/class.'.$app.'_'.$components[0].'.inc.php') ||
		// classes with an underscore in their name
		!isset($GLOBALS['egw_info']['apps'][$app]) && isset($GLOBALS['egw_info']['apps'][$app . '_' . $components[0]]) &&
			file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'_'.$components[0].'/inc/class.'.$class.'.inc.php') ||
		// eGW api classes using the old naming schema, eg. html
		file_exists($file = EGW_API_INC.'/class.'.$class.'.inc.php') ||
		// eGW api classes containing multiple classes in on file, eg. egw_exception
		isset($components[0]) && file_exists($file = EGW_API_INC.'/class.'.$app.'_'.$components[0].'.inc.php') ||
		// eGW eTemplate classes using the old naming schema, eg. etemplate
		file_exists($file = EGW_INCLUDE_ROOT.'/etemplate/inc/class.'.$class.'.inc.php') ||
		// include PEAR and PSR0 classes from include_path
		// need to use include (not include_once) as eg. a previous included EGW_API_INC/horde/Horde/String.php causes
		// include_once('Horde/String.php') to return true, even if the former was included with an absolute path
		// only use include_path, if no Composer vendor directory exists
		!isset($GLOBALS['egw_info']['apps'][$app]) && !file_exists(EGW_SERVER_ROOT.'/vendor') &&
			@include($file = strtr($class, array('_'=>'/','\\'=>'/')).'.php'))
	{
		include_once($file);
		//if (!class_exists($class, false) && !interface_exists($class, false)) error_log("autoloading class $class by include_once($file) failed!");
	}
	// allow apps to define an own autoload method
	elseif (isset($GLOBALS['egw_info']['flags']['autoload']) && is_callable($GLOBALS['egw_info']['flags']['autoload']))
	{
		call_user_func($GLOBALS['egw_info']['flags']['autoload'],$class);
	}
});

// if we have a Composer vendor directory, also load it's autoloader, to allow manage our requirements with Composer
if (file_exists(EGW_SERVER_ROOT.'/vendor'))
{
	require_once EGW_SERVER_ROOT.'/vendor/autoload.php';
}

/**
 * Clasify exception for a headline and log it to error_log, if not running as cli
 *
 * @param Exception $e
 * @param string &$headline
 */
function _egw_log_exception(Exception $e,&$headline=null)
{
	$trace = explode("\n", $e->getTraceAsString());
	if ($e instanceof egw_exception_no_permission)
	{
		$headline = try_lang('Permission denied!');
	}
	elseif ($e instanceof egw_exception_db)
	{
		$headline = try_lang('Database error');
	}
	elseif ($e instanceof egw_exception_wrong_userinput)
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
		error_log($headline.($e instanceof egw_exception_warning ? ': ' : ' ('.get_class($e).'): ').$e->getMessage());
		foreach($trace as $line)
		{
			error_log($line);
		}
		error_log('# Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid'].
			', Request='.$_SERVER['REQUEST_METHOD'].' '.($_SERVER['HTTPS']?'https://':'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].
			', User-agent='.$_SERVER['HTTP_USER_AGENT']);
	}
}

/**
 * Fail a little bit more gracefully then an uncought exception
 *
 * Does NOT return
 *
 * @param Exception $e
 */
function egw_exception_handler(Exception $e)
{
	// handle redirects without logging
	if (is_a($e, 'egw_exception_redirect'))
	{
		egw::redirect($e->url, $e->app);
	}
	// logging all exceptions to the error_log (if not cli) and get headline
	$headline = null;
	_egw_log_exception($e,$headline);

	// exception handler for cli (command line interface) clients, no html, no logging
	if(!isset($_SERVER['HTTP_HOST']) || $GLOBALS['egw_info']['flags']['no_exception_handler'] == 'cli')
	{
		echo ($headline ? $headline.': ' : '').$e->getMessage()."\n";
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
		$message = '<h3>'.html::htmlspecialchars($headline)."</h3>\n".
			'<pre><b>'.html::htmlspecialchars($e->getMessage())."</b>\n\n";

		// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
		if ($GLOBALS['egw_info']['server']['exception_show_trace'])
		{
			$message .= html::htmlspecialchars($e->getTraceAsString());
		}
		$message .= "</pre>\n";
		if (is_a($e, 'egw_exception_db_setup'))
		{
			$setup_dir = str_replace(array('home/index.php','index.php'),'setup/',$_SERVER['PHP_SELF']);
			$message .= '<a href="'.$setup_dir.'">Run setup to install or configure EGroupware.</a>';
		}
		elseif (is_object($GLOBALS['egw']) && isset($GLOBALS['egw']->session) && method_exists($GLOBALS['egw'],'link'))
		{
			$message .= '<p><a href="'.$GLOBALS['egw']->link('/index.php').'">'.try_lang('Click here to resume your eGroupWare Session.').'</a></p>';
		}
		if (is_object($GLOBALS['egw']) && isset($GLOBALS['egw']->framework))
		{
			$GLOBALS['egw']->framework->render($message,$headline);
		}
		else
		{
			echo "<html>\n<head>\n<title>".html::htmlspecialchars($headline)."</title>\n</head>\n<body>\n$message\n</body>\n</html>\n";
		}
	}
	// exception handler sending message back to the client as basic auth message
	elseif($GLOBALS['egw_info']['flags']['no_exception_handler'] == 'basic_auth')
	{
		$error = str_replace(array("\r", "\n"), array('', ' | '), $e->getMessage());
		header('WWW-Authenticate: Basic realm="'.$headline.' '.$error.'"');
		header('HTTP/1.1 401 Unauthorized');
		header('X-WebDAV-Status: 401 Unauthorized', true);
	}
	if (is_object($GLOBALS['egw']))
	{
		common::egw_exit();
	}
	exit;
}

if (!isset($GLOBALS['egw_info']['flags']['no_exception_handler']) || $GLOBALS['egw_info']['flags']['no_exception_handler'] !== true)
{
	set_exception_handler('egw_exception_handler');
}

/**
 * Fail a little bit more gracefully then a catchable fatal error, by throwing an exception
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
			throw new ErrorException($errstr, $errno, 0, $errfile, $errline);

		case E_WARNING:
		case E_USER_WARNING:
			// skip message for warnings supressed via @-error-control-operator (eg. @is_dir($path))
			// can be commented out to get suppressed warnings too!
			if (error_reporting())
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

// some not longer necessary defines
if (isset($GLOBALS['egw_info']['flags']['phpgw_compatibility']) && $GLOBALS['egw_info']['flags']['phpgw_compatibility'])
{
	define('PHPGW_API_INC',EGW_API_INC);
	define('PHPGW_SERVER_ROOT',EGW_SERVER_ROOT);
	define('PHPGW_INCLUDE_ROOT',EGW_INCLUDE_ROOT);

	/* debugging settings */
	define('DEBUG_DATATYPES',  True);
	define('DEBUG_LEVEL',  3);
	define('DEBUG_OUTPUT', 2); /* 1 = screen,  2 = DB. For both use 3. */
	define('DEBUG_TIMER', False);
}
