<?php
	 /**************************************************************************\
	 * phpGroupWare API - phpgwapi loader                                       *
	 * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	 * and Joseph Engo <jengo@phpgroupware.org>                                 *
	 * Has a few functions, but primary role is to load the phpgwapi            *
	 * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	 * -------------------------------------------------------------------------*
	 * This library is part of the phpGroupWare API                             *
	 * http://www.phpgroupware.org/api                                          * 
	 * ------------------------------------------------------------------------ *
	 * This library is free software; you can redistribute it and/or modify it  *
	 * under the terms of the GNU Lesser General Public License as published by *
	 * the Free Software Foundation; either version 2.1 of the License,         *
	 * or any later version.                                                    *
	 * This library is distributed in the hope that it will be useful, but      *
	 * WITHOUT ANY WARRANTY; without even the implied warranty of               *
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	 * See the GNU Lesser General Public License for more details.              *
	 * You should have received a copy of the GNU Lesser General Public License *
	 * along with this library; if not, write to the Free Software Foundation,  *
	 * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	 \**************************************************************************/

	/* $Id$ */

	/****************************************************************************\
	 * Direct functions which are not part of the API classes                    *
	 * because they are required to be available at the lowest level.            *
	 \***************************************************************************/
	/*!
	 @collection_start direct functions
	 @abstract Direct functions which are not part of the API classes because they are required to be available at the lowest level.
	*/
	/*!
	 @function print_debug_subarray
	 @abstract Not to be used directly. Should only be used by print_debug()
	*/
	function print_debug_subarray($array)
	{
		while(list($key, $value) = each($array))
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

	/*!
	 @function print_debug
	 @abstract print debug data only when debugging mode is turned on.
	 @author seek3r
	 @discussion This function is used to debugging data. 
	 @syntax print_debug('message', $somevar);
	 @example print_debug('this is some debugging data',$somevar);
	*/
	function print_debug($message,$var = 'messageonly',$part = 'app', $level = 3)
	{
		if (($part == 'app' && EXP_DEBUG_APP == True) || ($part == 'api' && DEBUG_API == True))
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
						while(list($key, $value) = each($var))
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

	/*!
	 @function sanitize
	 @abstract Validate data.
	 @author seek3r
	 @discussion This function is used to validate input data. 
	 @syntax sanitize('type', 'match string');
	 @example sanitize('number',$somestring);
	*/

	/*
	$GLOBALS['phpgw_info']['server']['sanitize_types']['number'] = Array('type' => 'preg_match', 'string' => '/^[0-9]+$/i');
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
				break;
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
				if (eregi("^[0-9]{1,3}(\.[0-9]{1,3}){3}$",$string))
				{
					$octets = split('\.',$string);
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
				break;
			case 'file':
				if (preg_match("/^[a-z0-9_]+\.+[a-z]+$/i", $string))
				{
					return True;
				}
				break;
			case 'email':
				if (eregi("^([[:alnum:]_%+=.-]+)@([[:alnum:]_.-]+)\.([a-z]{2,3}|[0-9]{1,3})$",$string))
				{
					return True;
				}
				break;
			case 'password':
				$password_length = strlen($string);
				$password_numbers = Array('0','1','2','3','4','5','6','7','8','9');
				$password_special_chars = Array(' ','~','`','!','@','#','$','%','^','&','*','(',')','_','+','-','=','{','}','|','[',']',"\\",':','"',';',"'",'<','>','?',',','.','/');

				if(@isset($GLOBALS['phpgw_info']['server']['pass_min_length']) && is_int($GLOBALS['phpgw_info']['server']['pass_min_length']) && $GLOBALS['phpgw_info']['server']['pass_min_length'] > 1)
				{
					$min_length = $GLOBALS['phpgw_info']['server']['pass_min_length'];
				}
				else
				{
					$min_length = 1;
				}

				if(@isset($GLOBALS['phpgw_info']['server']['pass_require_non_alpha']) && $GLOBALS['phpgw_info']['server']['pass_require_non_alpha'] == True)
				{
					$pass_verify_non_alpha = False;
				}
				else
				{
					$pass_verify_non_alpha = True;
				}
				
				if(@isset($GLOBALS['phpgw_info']['server']['pass_require_numbers']) && $GLOBALS['phpgw_info']['server']['pass_require_numbers'] == True)
				{
					$pass_verify_num = False;
				}
				else
				{
					$pass_verify_num = True;
				}

				if(@isset($GLOBALS['phpgw_info']['server']['pass_require_special_char']) && $GLOBALS['phpgw_info']['server']['pass_require_special_char'] == True)
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
						$GLOBALS['phpgw_info']['flags']['msgbox_data']['Password requires at least one non-alpha character']=False;
					}

					if ($pass_verify_num == False)
					{
						$GLOBALS['phpgw_info']['flags']['msgbox_data']['Password requires at least one numeric character']=False;
					}

					if ($pass_verify_special_char == False)
					{
						$GLOBALS['phpgw_info']['flags']['msgbox_data']['Password requires at least one special character (non-letter and non-number)']=False;
					}
					
					if ($pass_verify_num == True && $pass_verify_special_char == True)
					{
						return True;
					}
					return False;
				}
				$GLOBALS['phpgw_info']['flags']['msgbox_data']['Password must be at least '.$min_length.' characters']=False;
				return False;
				break;
			case 'any':
				return True;
				break;
			default :
				if (isset($GLOBALS['phpgw_info']['server']['sanitize_types'][$type]['type']))
				{
					if ($GLOBALS['phpgw_info']['server']['sanitize_types'][$type]['type']($GLOBALS['phpgw_info']['server']['sanitize_types'][$type]['string'], $string))
					{
						return True;
					}
				}
				return False;
		}
	}

	function reg_var($varname, $method = 'any', $valuetype = 'alphanumeric',$default_value='',$register=True)
	{
		if($method == 'any')
		{
			$method = Array('POST','GET','COOKIE','SERVER','GLOBAL','DEFAULT');
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
					if(phpversion() >= '4.2.0')
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
			$GLOBALS['phpgw_info'][$GLOBALS['phpgw_info']['flags']['currentapp']][$varname] = $result;
		}
		return $result;
	}

	/*!
	 @function get_var
	 @abstract retrieve a value from either a POST, GET, COOKIE, SERVER or from a class variable.
	 @author skeeter
	 @discussion This function is used to retrieve a value from a user defined order of methods. 
	 @syntax get_var('id',array('HTTP_POST_VARS'||'POST','HTTP_GET_VARS'||'GET','HTTP_COOKIE_VARS'||'COOKIE','GLOBAL','DEFAULT'));
	 @example $this->id = get_var('id',array('HTTP_POST_VARS'||'POST','HTTP_GET_VARS'||'GET','HTTP_COOKIE_VARS'||'COOKIE','GLOBAL','DEFAULT'));
	 @param $variable name
	 @param $method ordered array of methods to search for supplied variable
	 @param $default_value (optional)
	*/
	function get_var($variable,$method='any',$default_value='')
	{
		return reg_var($variable,$method,'any',$default_value,False);
	}

	/*!
	 @function include_class
	 @abstract This will include the class once and guarantee that it is loaded only once.  Similar to CreateObject, but does not instantiate the class.
	 @author skeeter
	 @discussion This will include the API class once and guarantee that it is loaded only once.  Similar to CreateObject, but does not instantiate the class.
	 @syntax include_class('setup');
	 @example include_class('setup');
	 @param $included_class API class to load
	*/
	function include_class($included_class)
	{
		if (!isset($GLOBALS['phpgw_info']['flags']['included_classes'][$included_class]) ||
			!$GLOBALS['phpgw_info']['flags']['included_classes'][$included_class])
		{
			$GLOBALS['phpgw_info']['flags']['included_classes'][$included_class] = True;   
			include(PHPGW_SERVER_ROOT.'/phpgwapi/inc/class.'.$included_class.'.inc.php');
		}
	}

	/*!
	 @function CreateObject
	 @abstract Load a class and include the class file if not done so already.
	 @author mdean
	 @author milosch
	 @author (thanks to jengo and ralf)
	 @discussion This function is used to create an instance of a class, and if the class file has not been included it will do so. 
	 @syntax CreateObject('app.class', 'constructor_params');
	 @example $phpgw->acl = CreateObject('phpgwapi.acl');
	 @param $classname name of class
	 @param $p1-$p16 class parameters (all optional)
	*/
	function CreateObject($class,
		$p1='_UNDEF_',$p2='_UNDEF_',$p3='_UNDEF_',$p4='_UNDEF_',
		$p5='_UNDEF_',$p6='_UNDEF_',$p7='_UNDEF_',$p8='_UNDEF_',
		$p9='_UNDEF_',$p10='_UNDEF_',$p11='_UNDEF_',$p12='_UNDEF_',
		$p13='_UNDEF_',$p14='_UNDEF_',$p15='_UNDEF_',$p16='_UNDEF_')
	{
		global $phpgw_info, $phpgw;

		if (is_object(@$GLOBALS['phpgw']->log) && $class != 'phpgwapi.error' && $class != 'phpgwapi.errorlog')
		{
			//$GLOBALS['phpgw']->log->write(array('text'=>'D-Debug, dbg: %1','p1'=>'This class was run: '.$class,'file'=>__FILE__,'line'=>__LINE__));
		}

		/* error_reporting(0); */
		list($appname,$classname) = explode('.', $class);

		if (!isset($GLOBALS['phpgw_info']['flags']['included_classes'][$classname]) ||
			!$GLOBALS['phpgw_info']['flags']['included_classes'][$classname])
		{
			if(@file_exists(PHPGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php'))
			{
				include(PHPGW_INCLUDE_ROOT.'/'.$appname.'/inc/class.'.$classname.'.inc.php');
				$GLOBALS['phpgw_info']['flags']['included_classes'][$classname] = True;
			}
			else
			{
				$GLOBALS['phpgw_info']['flags']['included_classes'][$classname] = False;
			}
		}
		if($GLOBALS['phpgw_info']['flags']['included_classes'][$classname])
		{
			if ($p1 == '_UNDEF_' && $p1 != 1)
			{
				eval('$obj = new ' . $classname . ';');
			}
			else
			{
				$input = array($p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p9,$p10,$p11,$p12,$p13,$p14,$p15,$p16);
				$i = 1;
				$code = '$obj = new ' . $classname . '(';
				while (list($x,$test) = each($input))
				{
					if (($test == '_UNDEF_' && $test != 1 ) || $i == 17)
					{
						break;
					}
					else
					{
						$code .= '$p' . $i . ',';
					}
					$i++;
				}
				$code = substr($code,0,-1) . ');';
				eval($code);
			}
			/* error_reporting(E_ERROR | E_WARNING | E_PARSE); */
			return $obj;
		}
	}

	/*!
	 @function ExecMethod
	 @abstract Execute a function, and load a class and include the class file if not done so already.
	 @author seek3r
	 @discussion This function is used to create an instance of a class, and if the class file has not been included it will do so.
	 @syntax ExecObject('app.class', 'constructor_params');
	 @param $method to execute
	 @param $functionparams function param should be an array
	 @param $loglevel developers choice of logging level
	 @param $classparams params to be sent to the contructor
	 @example ExecObject('phpgwapi.acl.read');
	*/
	function ExecMethod($method, $functionparams = '_UNDEF_', $loglevel = 3, $classparams = '_UNDEF_')
	{
		/* Need to make sure this is working against a single dimensional object */
		$partscount = count(explode('.',$method)) - 1;
		if ($partscount == 2)
		{
			list($appname,$classname,$functionname) = explode(".", $method);
			if (!is_object($GLOBALS[$classname]))
			{
				if ($classparams != '_UNDEF_' && ($classparams || $classparams != 'True'))
				{
					$GLOBALS[$classname] = CreateObject($appname.'.'.$classname, $classparams);
				}
				else
				{
					$GLOBALS[$classname] = CreateObject($appname.'.'.$classname);
				}
			}

			if ((is_array($functionparams) || $functionparams != '_UNDEF_') && ($functionparams || $functionparams != 'True'))
			{
				return $GLOBALS[$classname]->$functionname($functionparams);
			}
			else
			{
				return $GLOBALS[$classname]->$functionname();
			}
		}
		/* if the $method includes a parent class (multi-dimensional) then we have to work from it */
		elseif ($partscount >= 3)
		{
			$GLOBALS['methodparts'] = explode(".", $method);
			$classpartnum = $partscount - 1;
			$appname = $GLOBALS['methodparts'][0];
			$classname = $GLOBALS['methodparts'][$classpartnum];
			$functionname = $GLOBALS['methodparts'][$partscount];
			/* Now I clear these out of the array so that I can do a proper */
			/* loop and build the $parentobject */
			unset ($GLOBALS['methodparts'][0]);
			unset ($GLOBALS['methodparts'][$classpartnum]);
			unset ($GLOBALS['methodparts'][$partscount]);
			reset ($GLOBALS['methodparts']);
			$firstparent = 'True';
			while (list ($key, $val) = each ($GLOBALS['methodparts']))
			{
				if ($firstparent == 'True')
				{
					$parentobject = '$GLOBALS["'.$val.'"]';
					$firstparent = False;
				}
				else
				{
					$parentobject .= '->'.$val;
				}
			}
			unset($GLOBALS['methodparts']);
			$code = '$isobject = is_object('.$parentobject.'->'.$classname.');';
			eval ($code);
			if (!$isobject)
			{
				if ($classparams != '_UNDEF_' && ($classparams || $classparams != 'True'))
				{
					if (is_string($classparams))
					{
						eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'", "'.$classparams.'");');
					}
					else
					{
						eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'", '.$classparams.');');
					}
				}
				else
				{
					eval($parentobject.'->'.$classname.' = CreateObject("'.$appname.'.'.$classname.'");');
				}
			}

			if ($functionparams != '_UNDEF_' && ($functionparams || $functionparams != 'True'))
			{
				eval('$returnval = '.$parentobject.'->'.$classname.'->'.$functionname.'('.$functionparams.');');
				return $returnval;
			}
			else
			{
				eval('$returnval = '.$parentobject.'->'.$classname.'->'.$functionname.'();');
				return $returnval;
			}
		}
		else
		{
			return 'error in parts';
		}
	}

	/*!
	 @function get_account_id
	 @abstract Return a properly formatted account_id.
	 @author skeeter
	 @discussion This function will return a properly formatted account_id. This can take either a name or an account_id as paramters. If a name is provided it will return the associated id.
	 @syntax get_account_id($accountid);
	 @example $account_id = get_account_id($accountid);
	 @param $account_id either a name or an id
	 @param $default_id either a name or an id
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
				return (isset($GLOBALS['phpgw_info']['user']['account_id'])?$GLOBALS['phpgw_info']['user']['account_id']:0);
			}
			elseif (is_string($default_id))
			{
				return $GLOBALS['phpgw']->accounts->name2id($default_id);
			}
			return intval($default_id);
		}
		elseif (is_string($account_id))
		{
			if($GLOBALS['phpgw']->accounts->exists(intval($account_id)) == True)
			{
				return intval($account_id);
			}
			else
			{
				return $GLOBALS['phpgw']->accounts->name2id($account_id);
			}
		}
	}

	/*!
	 @function filesystem_separator
	 @abstract sets the file system seperator depending on OS
	 @result file system separator
	*/
	function filesystem_separator()
	{
		if (PHP_OS == 'Windows' || PHP_OS == 'OS/2')
		{
			return '\\';
		}
		else
		{
			return '/';
		}
	}

	/* Just a wrapper to my new print_r() function I added to the php3 support file.  Seek3r */
	function _debug_array($array,$print=True)
	{
		$four = False;
		if(@floor(phpversion()) == 4)
		{
			$four = True;
		}
		if($four)
		{
			if(!$print)
			{
				ob_start();
			}
			echo '<pre>';
			print_r($array);
			echo '</pre>';
			if(!$print)
			{
				$v = ob_get_contents();
				ob_end_clean();
				return $v;
			}
		}
		else
		{
			return print_r($array,False,$print);
		}
	}

	/*
	@function alessthanb
	@abstract phpgw version checking, is param 1 < param 2 in phpgw versionspeak?
	@param	$a	phpgw version number to check if less than $b
	@param	$b	phpgw version number to check $a against
	#return	True if $a < $b
	*/
	function alessthanb($a,$b,$DEBUG=False)
	{
		$num = array('1st','2nd','3rd','4th');

		if ($DEBUG)
		{
			echo'<br>Input values: '
				. 'A="'.$a.'", B="'.$b.'"';
		}
		$newa = ereg_replace('pre','.',$a);
		$newb = ereg_replace('pre','.',$b);
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
			if ($DEBUG) { echo'<br>Checking if '. intval($testa[$i]) . ' is less than ' . intval($testb[$i]) . ' ...'; }
			if (intval($testa[$i]) < intval($testb[$i]))
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
			elseif(intval($testa[$i]) > intval($testb[$i]))
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

	/*!
	@function amorethanb
	@abstract phpgw version checking, is param 1 > param 2 in phpgw versionspeak?
	@param	$a	phpgw version number to check if more than $b
	@param	$b	phpgw version number to check $a against
	#return	True if $a < $b
	*/
	function amorethanb($a,$b,$DEBUG=False)
	{
		$num = array('1st','2nd','3rd','4th');

		if ($DEBUG)
		{
			echo'<br>Input values: '
				. 'A="'.$a.'", B="'.$b.'"';
		}
		$newa = ereg_replace('pre','.',$a);
		$newb = ereg_replace('pre','.',$b);
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
			if ($DEBUG) { echo'<br>Checking if '. intval($testa[$i]) . ' is more than ' . intval($testb[$i]) . ' ...'; }
			if (intval($testa[$i]) > intval($testb[$i]))
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
			elseif(intval($testa[$i]) < intval($testb[$i]))
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
?>
