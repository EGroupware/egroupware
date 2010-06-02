<?php
  /**************************************************************************\
  * eGroupWare API - Template class                                          *
  * (C) Copyright 1999-2000 NetUSE GmbH Kristian Koehntopp                   *
  * ------------------------------------------------------------------------ *
  * This is not part of eGroupWare, but is used by eGroupWare.               * 
  * http://www.egroupware.org/                                               * 
  * ------------------------------------------------------------------------ *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published    *
  * by the Free Software Foundation; either version 2.1 of the License, or   *
  * any later version.                                                       *
  \**************************************************************************/

  /* $Id$ */

	class Template
	{
		var $classname = 'Template';

		/**
		 * @var $rb_debug mixed False for no debug, string or array of strings with:
		 *	- function-name like set_var to get eg. all assignments or
		 *	- handle- / variable-names - if you are only interested in some variables ;-)
		 */
		var $debug = False;	// array('cat_list','cat_list_t');

		/* $file[handle] = 'filename'; */
		var $file = array();

		/* relative filenames are relative to this pathname */
		var $root = '';

		/* $varkeys[key] = 'key'; $varvals[key] = 'value'; */
		var $varkeys = array();
		var $varvals = array();

		/* 'remove'  => remove undefined variables
		 * 'comment' => replace undefined variables with comments
		 * 'keep'    => keep undefined variables
		 */
		var $unknowns = 'remove';

		/* 'yes' => halt, 'report' => report error, continue, 'no' => ignore error quietly */
		var $halt_on_error = 'yes';

		/* last error message is retained here */
		var $last_error = '';

		// if true change all phpGroupWare into eGroupWare in set_var
		var $egroupware_hack = False;

		/***************************************************************************/
		/* public: Constructor.
		 * root:     template directory.
		 * unknowns: how to handle unknown variables.
		 */
		function Template($root = '.', $unknowns = 'remove')
		{
			$this->set_root($root);
			$this->set_unknowns($unknowns);
		}

		/* public: setroot(pathname $root)
		 * root:   new template directory.
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

		/* public: set_unknowns(enum $unknowns)
		 * unknowns: 'remove', 'comment', 'keep'
		 *
		 */
		function set_unknowns($unknowns = 'keep')
		{
			if ($this->debug && $this->check_debug('set_unknows'))
			{
				echo "<p>Template::set_unknows('$unknowns')</p>\n";
			}
			$this->unknowns = $unknowns;
		}

		/* public: set_file(array $filelist)
		 * filelist: array of handle, filename pairs.
		 *
		 * public: set_file(string $handle, string $filename)
		 * handle: handle for a filename,
		 * filename: name of template file
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

		/* public: set_block(string $parent, string $handle, string $name = '')
		 * extract the template $handle from $parent, 
		 * place variable {$name} instead.
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
			$qhandle = preg_quote($handle);
			$reg = "/<!--\\s+BEGIN $qhandle\\s+-->(.*)\n\\s*<!--\\s+END $qhandle\\s+-->/s";
			if (!preg_match($reg,$str,$match))
			{
				// unfortunaly some apps set non-existing blocks, therefor I have to disable this diagnostics again for now
				$this->halt("set_block: unable to find block '$handle' in '$parent'=<pre>".htmlspecialchars($str)."</pre> this->root=$this->root");
				// return False;
			}
			$this->set_var($handle,$match[1]);
			$this->set_var($parent,preg_replace($reg, '{' . "$name}",$str));
		}

		/* public: set_var(array $values)
		 * values: array of variable name, value pairs.
		 *
		 * public: set_var(string $varname, string $value)
		 * varname: name of a variable that is to be defined
		 * value:   value of that variable
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

		/* public: subst(string $handle)
		 * handle: handle of template where variables are to be substituted.
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
				$str = str_replace($v, $this->varvals[$k], $str);
			}
			return $str;
		}

		/* public: psubst(string $handle)
		 * handle: handle of template where variables are to be substituted.
		 */
		function psubst($handle)
		{
			print $this->subst($handle);
			return false;
		}

		/* public: parse(string $target, string $handle, boolean append)
		 * target: handle of variable to generate
		 * handle: handle of template to substitute
		 * append: append to target handle
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
				foreach($handle as $i => $h)
				{
					$str = $this->subst($h);
					$this->set_var($target, $str);
				}
			}
			return $str;
		}

		function pparse($target, $handle, $append = false)
		{
			print $this->parse($target, $handle, $append);
			return false;
		}

		/* This is short for finish parse */
		function fp($target, $handle, $append = False)
		{
			return $this->finish($this->parse($target, $handle, $append));
		}

		/* This is a short cut for print finish parse */
		function pfp($target, $handle, $append = False)
		{
			echo $this->finish($this->parse($target, $handle, $append));
		}

		/* public: get_vars()
		 */
		function get_vars()
		{
			foreach($this->varkeys as $k => $v)
			{
				$result[$k] = $this->varvals[$k];
			}
			return $result;
		}

		/* public: get_var(string varname)
		 * varname: name of variable.
		 *
		 * public: get_var(array varname)
		 * varname: array of variable names
		 */
		function get_var($varname)
		{
			if (!is_array($varname))
			{
				return $this->varvals[$varname];
			}
			else
			{
				foreach($varname as $k => $v)
				{
					$result[$k] = $this->varvals[$k];
				}
				return $result;
			}
		}

		/* public: get_undefined($handle)
		 * handle: handle of a template.
		 */
		function get_undefined($handle)
		{
			if (!$this->loadfile($handle))
			{
				$this->halt("get_undefined: unable to load $handle.");
				return false;
			}

			preg_match_all("/\{([^}]+)\}/", $this->get_var($handle), $m);
			$m = $m[1];
			if (!is_array($m))
			{
				return false;
			}
			foreach($m as $k => $v)
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

		/* public: finish(string $str)
		 * str: string to finish.
		 */
		function finish($str)
		{
			switch ($this->unknowns)
			{
				case 'keep':
					break;
				case 'remove':
					$str = preg_replace('/{[^ \t\r\n}]+}/', '', $str);
					break;
				case 'comment':
					$str = preg_replace('/{([^ \t\r\n}]+)}/', "<!-- Template $handle: Variable \\1 undefined -->", $str);
					break;
			}

			return $str;
		}

		/* public: p(string $varname)
		 * varname: name of variable to print.
		 */
		function p($varname)
		{
			print $this->finish($this->get_var($varname));
		}

		function get($varname)
		{
			return $this->finish($this->get_var($varname));
		}

		/***************************************************************************/
		/* private: filename($filename)
		 * filename: name to be completed.
		 */
		function filename($filename,$root='',$time=1)
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
//					$new_root = str_replace($GLOBALS['phpgw_info']['server']['template_set'],'default',$root);
//					$new_root = substr($root, 0, strrpos($root, $GLOBALS['phpgw_info']['server']['template_set'])).'default';
//					$new_root = substr($root, 0, strlen($root) - strlen($GLOBALS['phpgw_info']['server']['template_set'])) . 'default';
					$new_root = dirname($root) . DIRECTORY_SEPARATOR . 'default';
					$new_filename = $this->filename(str_replace($root.'/','',$new_filename),$new_root,2);
				}
			}
			return $new_filename;
		}

		/* private: varname($varname)
		 * varname: name of a replacement variable to be protected.
		 */
		function varname($varname)
		{
			return '{'.$varname.'}';
		}

		/* private: loadfile(string $handle)
		 * handle:  load file defined by handle, if it is not loaded yet.
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
					// ignore error quitely
					break;
				case 'report':
					printf("<b>Template Error:</b> %s<br>\n", $msg);
					echo "<b>Backtrace</b>: ".function_backtrace(2)."<br>\n";
					break;
				case 'yes':
					throw new egw_exception_wrong_parameter('Template Error: '.$msg);
			}
		}

		function check_debug($str)
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
	}
