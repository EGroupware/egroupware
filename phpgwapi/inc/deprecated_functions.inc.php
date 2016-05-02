<?php
/**
 * EGroupware deprecated global functions
 *
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
 * print debug data only when debugging mode is turned on.
 *
 * @author seek3r
 * This function is used to debugging data.
 * print_debug('this is some debugging data',$somevar);
 */
function print_debug($message,$var = 'messageonly',$part = 'app', $level = 3)
{
	unset($message, $var, $part, $level);
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
