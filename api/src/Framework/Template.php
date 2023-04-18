<?php
/**
 * EGroupware API - Old phplib templates
 *
 * @copyright (c) Copyright 1999-2000 NetUSE GmbH Kristian Koehntopp
 * @license https://opensource.org/licenses/LGPL-2.1 GNU Lesser General Public License, version 2.1
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 2.1 of the License, or
 * any later version.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;

/**
 * Old phplib templates: DO NOT USE FOR ANY NEW DEVELOPMENT, use eTemplate2!
 */
class Template
{
	var $classname = 'Template';

	/**
	 * @var $debug mixed False for no debug, string or array of strings with:
	 *	- function-name like set_var to get e.g. all assignments or
	 *	- handle- / variable-names - if you are only interested in some variables ;-)
	 */
	var $debug = False;	// array('cat_list','cat_list_t');

	/**
	 * $file[handle] = 'filename';
	 */
	var $file = array();

	/* relative filenames are relative to this pathname */
	var $root = '';

	/* $varkeys[key] = 'key'; $varvals[key] = 'value'; */
	var $varkeys = array();
	var $varvals = array();

	/**
	 * How to deal with undefined placeholder:
	 *
	 * 'remove'  => remove undefined variables
	 * 'comment' => replace undefined variables with comments
	 * 'keep'    => keep undefined variables
	 *
	 * @var string
	 */
	var $unknowns = 'remove';

	/**
	 * 'yes' => halt, 'report' => report error, continue, 'no' => ignore error quietly
	 *
	 * @var string
	 */
	var $halt_on_error = 'yes';

	/**
	 * last error message is retained here
	 *
	 * @var string
	 */
	var $last_error = '';

	// if true change all phpGroupWare into eGroupWare in set_var
	var $egroupware_hack = False;

	/**
	 * Constructor.
	 *
	 * @param string $root ='.' template directory.
	 * @param string $unknowns ='remove' how to handle unknown variables.
	 */
	function __construct($root = '.', $unknowns = 'remove')
	{
		$this->set_root($root);
		$this->set_unknowns($unknowns);
	}

	/**
	 * Set template directory
	 *
	 * @param string $root   new template directory.
	 */
	function set_root($root)
	{
		if ($this->debug && $this->check_debug('set_root'))
		{
			echo "<p>Template::set_root('$root')</p>\n";
		}
		if (!is_dir($root))
		{
			$this->halt("set_root: $root is not a directory.");
			return false;
		}
		$this->root = $root;
		return true;
	}

	/**
	 * How to deal with undefined placeholders
	 *
	 * @param string $unknowns 'remove', 'comment', 'keep'
	 */
	function set_unknowns($unknowns = 'keep')
	{
		if ($this->debug && $this->check_debug('set_unknows'))
		{
			echo "<p>Template::set_unknows('$unknowns')</p>\n";
		}
		$this->unknowns = $unknowns;
	}

	/**
	 * Set template/file to process
	 *
	 * @param string|string[} $handle handle for a filename,
	 * @param string $filename ='' name of template file
	 */
	function set_file($handle, $filename = '')
	{
		if ($this->debug && $this->check_debug('set_file',$handle,$filename))
		{
			echo "<p>Template::set_file('".print_r($handle,true)."','$filename')</p>\n";
		}
		if (!is_array($handle))
		{
			if ($filename == '')
			{
				$this->halt("set_file: For handle $handle filename is empty.");
				return false;
			}
			$this->file[$handle] = $this->filename($filename);
		}
		else
		{
			foreach($handle as $h => $f)
			{
				$this->file[$h] = $this->filename($f);
			}
		}
	}

	/**
	 * Extract the template $handle from $parent and place variable {$name} instead
	 *
	 * @param string $parent name of template containing $handle
	 * @param string $handle name of part
	 * @param string $name name of variable/placeholder
	 * @return boolean
	 */
	function set_block($parent, $handle, $name = '')
	{
		if ($this->debug && $this->check_debug('set_block',$parent,$handle,$name))
		{
			echo "<p>Template::set_block('$parent','$handle','$name')</p>\n";
		}
		if (!$this->loadfile($parent))
		{
			$this->halt("set_block: unable to load '$parent'.");
			return false;
		}
		if ($name == '')
		{
			$name = $handle;
		}
		$str = $this->get_var($parent);
		$qhandle = preg_quote($handle, '/');
		$reg = "/<!--\\s+BEGIN $qhandle\\s+-->(.*)\n\\s*<!--\\s+END $qhandle\\s+-->/s";
		$match = null;
		if (!preg_match($reg,$str,$match))
		{
			// unfortunately some apps set non-existing blocks, therefor I have to disable this diagnostics again for now
			$this->halt("set_block: unable to find block '$handle' in '$parent'=<pre>".htmlspecialchars($str)."</pre> this->root=$this->root");
			// return False;
		}
		$this->set_var($handle,$match[1]);
		$this->set_var($parent,preg_replace($reg, '{' . "$name}",$str));
	}

	/* public: set_var(array $values)
	 * values: array of variable name, value pairs.
	 *
	 * @param string|array $varname name of a variable to be defined or array with varname => value pairs
	 * @param string $value ='' value of that variable, if it's not an array
	 */
	function set_var($varname, $value = '')
	{
		if (!is_array($varname))
		{
			if (empty($varname))
			{
				return;
			}
			$varname = array(
				$varname => $value
			);
		}
		foreach($varname as $k => $v)
		{
			if (!empty($k))
			{
				if ($this->debug && $this->check_debug('set_var',$k))
				{
					echo "<p>Template::set_var('$k','$v')</p>\n";
				}
				$this->varkeys[$k] = $this->varname($k);
				$this->varvals[$k] = $this->egroupware_hack ? str_replace(
					array('phpGroupWare','www.phpgroupware.org'),
					array('eGroupWare','www.eGroupWare.org'),$v
				) : $v;
			}
		}
	}

	/**
	 * Substitute variables/placeholders and return result
	 *
	 * @param string $handle handle of template where variables are to be substituted
	 * @return string
	 */
	function subst($handle)
	{
		if ($this->debug && $this->check_debug('subst',$handle))
		{
			echo "<p>Template::subst('$handle')</p>\n";
		}
		if (!$this->loadfile($handle))
		{
			$this->halt("subst: unable to load $handle.");
			return false;
		}

		$str = $this->get_var($handle);
		foreach($this->varkeys as $k => $v)
		{
			$str = str_replace($v, $this->varvals[$k] ?? '', $str);
		}
		return $str;
	}

	/**
	 * Substitute variables/placeholders and print result
	 *
	 * @param string $handle handle of template where variables are to be substituted
	 * @return boolean false
	 */
	function psubst($handle)
	{
		print $this->subst($handle);

		return false;
	}

	/**
	 * Substitue variables/placeholders, insert result into variable and return it
	 *
	 * @param string $target handle of variable to generate
	 * @param string $handle handle of template where variables are to be substituted
	 * @param boolean $append =false true: append to target handle
	 * @return string
	 */
	function parse($target, $handle, $append = false)
	{
		if (!is_array($handle))
		{
			$str = $this->subst($handle);
			if ($append)
			{
				$this->set_var($target, $this->get_var($target) . $str);
			}
			else
			{
				$this->set_var($target, $str);
			}
		}
		else
		{
			foreach($handle as $h)
			{
				$str = $this->subst($h);
				$this->set_var($target, $str);
			}
		}
		return $str;
	}

	/**
	 * Substitue variables/placeholders, insert result into variable and print it
	 *
	 * @param string $target handle of variable to generate
	 * @param string $handle handle of template where variables are to be substituted
	 * @param boolean $append =false true: append to target handle
	 * @return boolean false
	 */
	function pparse($target, $handle, $append = false)
	{
		print $this->parse($target, $handle, $append);

		return false;
	}

	/**
	 * This is short for finish parse
	 */
	function fp($target, $handle, $append = False)
	{
		return $this->finish($this->parse($target, $handle, $append));
	}

	/**
	 * This is a shortcut for print finish parse
	 */
	function pfp($target, $handle, $append = False)
	{
		echo $this->finish($this->parse($target, $handle, $append));
	}

	/**
	 * Return all variables as array
	 *
	 * @return array
	 */
	function get_vars()
	{
		foreach(array_keys($this->varkeys) as $k)
		{
			$result[$k] = $this->varvals[$k];
		}
		return $result;
	}

	/**
	 * Return a single or multiple variable
	 *
	 * @param string|array $varname variable name or array of names as key!
	 * @return string|array value or array of values
	 */
	function get_var($varname)
	{
		if (!is_array($varname))
		{
			return $this->varvals[$varname];
		}
		else
		{
			foreach(array_keys($varname) as $k)
			{
				$result[$k] = $this->varvals[$k];
			}
			return $result;
		}
	}

	/**
	 * Return undefined variables/placeholders of a handle
	 *
	 * @param string handle handle of a template
	 * @return array|boolean array with undefined variables as key and value, or false if none
	 */
	function get_undefined($handle)
	{
		if (!$this->loadfile($handle))
		{
			$this->halt("get_undefined: unable to load $handle.");
			return false;
		}

		$matches = null;
		preg_match_all("/\{([^}]+)\}/", $this->get_var($handle), $matches);
		$m = $matches[1];
		if (!is_array($m))
		{
			return false;
		}
		foreach($m as $v)
		{
			if (!isset($this->varkeys[$v]))
			{
				$result[$v] = $v;
			}
		}

		if (count($result))
		{
			return $result;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Finish: remove or keep unknown variables/placeholders
	 *
	 * @param string $str string to finish
	 */
	function finish($str)
	{
		switch ($this->unknowns)
		{
			case 'keep':
				break;
			case 'remove':
				$str = preg_replace('/{[a-z0-9_-]+}/i', '', $str);
				break;
			case 'comment':
				$str = preg_replace('/{([a-z0-9_-]+)}/i', "<!-- Template variable \\1 undefined -->", $str);
				break;
		}

		return $str;
	}

	/**
	 * Finish and print a given variable
	 *
	 * @param string $varname name of variable to print.
	 */
	function p($varname)
	{
		print $this->finish($this->get_var($varname));
	}

	/**
	 * Finish and return a given variable
	 *
	 * @param string $varname name of variable to print.
	 */
	function get($varname)
	{
		return $this->finish($this->get_var($varname));
	}

	/**
	/*
	 * @param string $filename name to be completed
	 * @param string $root ='' default $this->root
	 * @param int $time =1
	 */
	protected function filename($filename,$root='',$time=1)
	{
		if($root == '')
		{
			$root = $this->root;
		}
		if(substr($filename, 0, 1) != '/')
		{
			$new_filename = $root . '/' . $filename;
		}
		else
		{
			$new_filename = $filename;
		}

		if (!file_exists($new_filename))
		{
			if($time==2)
			{
				$this->halt("filename: file $new_filename does not exist.");
			}
			else
			{
				$new_root = dirname($root) . DIRECTORY_SEPARATOR . 'default';
				$new_filename = $this->filename(str_replace($root.'/','',$new_filename),$new_root,2);
			}
		}
		return $new_filename;
	}

	/**
	 * @param string $varname name of a replacement variable to be protected
	 */
	protected function varname($varname)
	{
		return '{'.$varname.'}';
	}

	/**
	 * @param string $handle  load file defined by handle, if it is not loaded yet
	 */
	function loadfile($handle)
	{
		if ($this->debug && $this->check_debug('loadfile',$handle))
		{
			echo "<p>Template::loadfile('$handle') file=<pre>\n".print_r($this->file,True)."</pre>\n";
			echo "<p>backtrace: ".function_backtrace()."</p>\n";
		}
		if (isset($this->varkeys[$handle]) && !empty($this->varvals[$handle]))
		{
			return true;
		}
		if (!isset($this->file[$handle]))
		{
			if ($this->debug && $this->check_debug('loadfile',$handle))
			{
				echo "varkeys =<pre>".print_r($this->varkeys,True)."</pre>varvals =<pre>".print_r($this->varvals,True)."</pre>\n";
			}
			$this->halt("loadfile: $handle is not a valid handle.");
			return false;
		}
		$filename = $this->file[$handle];

		$str = file_get_contents($filename);
		if (empty($str))
		{
			$this->halt("loadfile: While loading $handle, $filename does not exist or is empty.");
			return false;
		}

		$this->set_var($handle, $str);
		return true;
	}

	/**
	 * Giving a error message and halt the execution (if $this->halt_on_error == 'yes')
	 *
	 * @param string $msg error message to show
	 */
	function halt($msg)
	{
		$this->last_error = $msg;

		switch ($this->halt_on_error)
		{
			case 'no':
				// ignore error quietly
				break;
			case 'report':
				printf("<b>Template Error:</b> %s<br>\n", $msg);
				echo "<b>Backtrace</b>: ".function_backtrace(2)."<br>\n";
				break;
			case 'yes':
				throw new Api\Exception\WrongParameter('Template Error: '.$msg);
		}
	}

	function check_debug()
	{
		if (!$this->debug) return False;

		foreach(func_get_args() as $arg)
		{
			if (!is_array($this->debug) && $this->debug === $arg ||
				(is_array($this->debug) && (@$this->debug[$arg] || in_array($arg,$this->debug,True))))
			{
				return True;
			}
		}
		return False;
	}

	/**
	 * get template dir of an application
	 *
	 * @param string $appname application name optional can be derived from $GLOBALS['egw_info']['flags']['currentapp'];
	 * @return string template directory
	 * @throws Api\Exception\WrongParameter if no directory is found
	 */
	static function get_dir($appname = '')
	{
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		if ($appname == 'logout' || $appname == 'login')
		{
			$appname = 'phpgwapi';
		}

		if (!isset($GLOBALS['egw_info']['server']['template_set']) && isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}

		// Setting this for display of template choices in user preferences
		if ($GLOBALS['egw_info']['server']['template_set'] == 'user_choice')
		{
			$GLOBALS['egw_info']['server']['usrtplchoice'] = 'user_choice';
		}

		if (($GLOBALS['egw_info']['server']['template_set'] == 'user_choice' ||
			!isset($GLOBALS['egw_info']['server']['template_set'])) &&
			isset($GLOBALS['egw_info']['user']['preferences']['common']['template_set']))
		{
			$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];
		}
		if (!file_exists(EGW_SERVER_ROOT.'/phpgwapi/templates/'.basename($GLOBALS['egw_info']['server']['template_set']).'/class.'.
			$GLOBALS['egw_info']['server']['template_set'].'_framework.inc.php') &&
			!file_exists(EGW_SERVER_ROOT.'/'.basename($GLOBALS['egw_info']['server']['template_set']).'/inc/class.'.
			$GLOBALS['egw_info']['server']['template_set'].'_framework.inc.php'))
		{
			$GLOBALS['egw_info']['server']['template_set'] = 'idots';
		}
		$tpldir         = EGW_SERVER_ROOT . '/' . $appname . '/templates/' . $GLOBALS['egw_info']['server']['template_set'];
		$tpldir_default = EGW_SERVER_ROOT . '/' . $appname . '/templates/default';

		if (@is_dir($tpldir))
		{
			return $tpldir;
		}
		elseif (@is_dir($tpldir_default))
		{
			return $tpldir_default;
		}
		throw new Api\Exception\WrongParameter("Template directory for app '$appname' not found!");
	}
}