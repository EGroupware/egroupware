<?php
/**
 * EGroupware deprecated factory methods: CreateObject, ExecMethod, ...
 *
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Load a class and include the class file if not done so already.
 *
 * This function is used to create an instance of a class, and if the class file has not been included it will do so.
 * $GLOBALS['egw']->acl = CreateObject('phpgwapi.acl');
 *
 * @author RalfBecker@outdoor-training.de
 * @param $classname name of class
 * @param $p1,$p2,... class parameters (all optional)
 * @deprecated use autoloadable class-names and new
 * @return object reference to an object
 */
function CreateObject($class)
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
			throw new Api\Exception\AssertionFailed(__FUNCTION__."($classname) file $f not found!");
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
 *
 * @param string app.class.method method to execute
 * @example ExecObject('etemplates.so_sql.search',$criteria,$key_only,...);
 * @deprecated use autoloadable class-names, instanciate and call method or use static methods
 * @return mixed reference to returnvalue of the method
 */
function &ExecMethod2($acm)
{
	if (!is_callable($acm))
	{
		list(,$class,$method) = explode('.',$acm);

		if (class_exists($class))
		{
			$obj = new $class;
		}
		else
		{
			$obj = CreateObject($acm);
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
 * @deprecated use autoloadable class-names, instanciate and call method or use static methods
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

		if ($classparams != '_UNDEF_' && ($classparams || $classparams != 'True'))
		{
			$obj = CreateObject($appname.'.'.$classname, $classparams);
		}
		elseif (class_exists($classname))
		{
			$obj = new $classname;
		}
		else
		{
			$obj = CreateObject($appname.'.'.$classname);
		}

		if (!method_exists($obj, $functionname))
		{
			error_log("ExecMethod('$method', ...) No methode '$functionname' in class '$classname'! ".function_backtrace());
			return false;
		}
		$method = array($obj, $functionname);
	}
	if (is_callable($method))
	{
		return $functionparam != '_UNDEF_' ? call_user_func($method,$functionparam) : call_user_func($method);
	}
	error_log("ExecMethod('$method', ...) Error in parts! ".function_backtrace());
	return false;
}
