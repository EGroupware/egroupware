<?php
  /**************************************************************************\
  * phpGroupWare API - Template class                                        *
  * (C) Copyright 2001 Ben Woodhead ben@echo-chn.net                         *
  * ------------------------------------------------------------------------ *
  * This is not part of phpGroupWare, but is used by phpGroupWare.           * 
  * http://www.phpgroupware.org/                                             * 
  * ------------------------------------------------------------------------ *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published    *
  * by the Free Software Foundation; either version 2.1 of the License, or   *
  * any later version.                                                       *
  \**************************************************************************/

	/*
	 * HTML template parser
	 * Ben Woodhead
	 * ben@echo-chn.net
	 * LGPL
	 * @package phpgwapi
	 */
	class Template
	{
		/** Internal Use - Array of file names */
		var $m_file     = array();
		/** Internal Use - Array of block names*/
		var $m_block    = array();
		/** Internal Use */
		var $m_varkeys  = array();
		/** Internal Use */
		var $m_varvals  = array();

		/** Internal Use - Path definition */
		var $m_root     = array();

		/** Internal Use - Defines what to do with unknown tags
		* @param keep, remove, or comment
		*/
		var $m_unknowns = '';
		/** Internal Use - class error instance */
		var $c_error    = '';

		/** Default constructor
		* @param path - Path to were templates are located (default in preferences)
		* @param unknown - Defines what to do with unknown tags (default in preferences)
		*/
		function Template($root='', $unknowns='')
		{
			global $preferences;

			$this->c_error = createObject('cError','1');
			$this->c_error->set_file(__FILE__);

			if (!empty($root))
			{
				$this->set_root($root);
			}
			else
			{
				$this->set_root($preferences['template']['path']);
			}
			if (!empty($unknowns))
			{
				$this->set_unknowns($unknowns);
			}
			else
			{
				$this->set_unknowns($preferences['template']['unknowns']);
			}
		}

		/** Set the path to templates
		* @param path - Path to were templates are located
		* @returns returns false if an error has occured
		*/
		function set_root($root)
		{
			if (!is_array($root))
			{
				if (!is_dir($root))
				{
					$this->c_error->halt("set_root (scalar): $root is not a directory.",0,__LINE__);
					return false;
				}
				$this->m_root[] = $root;
			}
			else
			{
				reset($root);
				while(list($k, $v) = each($root))
				{
					if (!is_dir($v))
					{
						$this->c_error->halt("set_root (array): $v (entry $k of root) is not a directory.",0,__LINE__);
						return false;
					}
					$this->m_root[] = $v;
				}
			}
			return true;
		}

		/** Sets what to do with unknown tags
		* @param unknown - Path to were templates are located
		* @notes keep - displays the unknown tags
		* @notes remove - removes unknown tags
		* @returns returns false if an error has occured
		*/
		function set_unknowns($unknowns='')
		{
			$this->m_unknowns = $unknowns;
		}

		/** Sets name of template file
		* @blockname - alias for the block
		* @filename - filename of block
		* @notes Must be passed in as array
		* @returns returns false if an error has occured
		*/
		function set_file($varname, $filename='')
		{
			if (!is_array($varname))
			{
				if ($filename == '')
				{
					$c_error->halt("set_file: For varname $varname filename is empty.",0,__LINE__);
					return false;
				}
				$this->m_file[$varname] = $this->filename($filename);
			}
			else
			{
				reset($varname);
				while(list($h, $f) = each($varname))
				{
					if ($f == '')
					{
						$this->c_error->halt("set_file: For varname $h filename is empty.",0,__LINE__);
						return false;
					}
					$this->m_file[$h] = $this->filename($f);
				}
			}
			return true;
		}

		/** Defines what block to use in template
		* @param parent - is the block alias
		* @param block - will look for this name in template file
		* @param name - alias for block (defaults to block name)
		* @notes Also can be used to define nested blocks
		* @returns Currently returns true
		*/
		function set_block($parent, $varname, $name='')
		{
			if ($name == '')
			{
				$name = $varname;
			}
			$this->m_block[$varname]['parent'] = $parent;
			$this->m_block[$varname]['alias']  = $name;
			return true;
		}

		/** Sets the tags in template
		* @param Tag name found in template file
		* @param Value that will be included when parsing complete
		* @return No return
		*/
		function set_var($varname, $value='')
		{
			if (!is_array($varname))
			{
				if (!empty($varname))
				{
					$this->m_varkeys[$varname] = '/' . $this->varname($varname) . '/';
					$this->m_varvals[$varname] = $value;
				}
			}
			else
			{
				reset($varname);
				while(list($k, $v) = each($varname))
				{
					if (!empty($k))
					{
						$this->m_varkeys[$k] = '/' . $this->varname($k) . '/';
						$this->m_varvals[$k] = $v;
					}
				}
			}
		}

		/** Substitute text in templates
		* @param Tag name found in template file
		* @return processed string
		* @notes Internal Use
		*/
		function subst($varname)
		{
			$str = $this->get_var($varname);
			$str = @preg_replace($this->m_varkeys, $this->m_varvals, $str);
			return $str;
		}

		/** Substitute text in templates and prints
		* @param Tag name found in template file
		* @notes Internal Use
		*/
		function psubst($varname)
		{
			print $this->subst($varname);
			return false;
		}

		/** Parse complete template
		* @param Target - Alias for processed text
		* @param Block - Name of block to process
		* @param Append - Should text be append to file (defaults no false)
		* @return processed string
		*/
		function parse($target, $varname, $append = false)
		{
			if (!is_array($varname))
			{
				$str = $this->subst($varname);
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
				reset($varname);
				while(list($i, $h) = each($varname))
				{
					$str = $this->subst($h);
					$this->set_var($target, $str);
				}
			}
			return $str;
		}

		/** Parse complete template and print results
		* @param Target - Alias for processed text
		* @param Block - Name of block to process
		* @param Append - Should text be append to file (defaults no false)
		*/
		function pparse($target, $varname, $append = false)
		{
			print $this->parse($target, $varname, $append);
			return false;
		}

		/** Gets the tags from the array
		* @notes Internal use
		*/
		function get_vars()
		{
			reset($this->m_varkeys);
			while(list($k, $v) = each($this->m_varkeys))
			{
				$result[$k] = $this->get_var($k);
			}

			return $result;
		}

		/** Gets the tags from the single input
		* @notes Internal use
		* @returns Result array
		*/
		function get_var($varname)
		{
			if (!is_array($varname))
			{
				if (!isset($this->m_varkeys[$varname]) or empty($this->m_varvals[$varname]))
				{
					if (isset($this->m_file[$varname]))
					{
						$this->loadfile($varname);
					}
					if (isset($this->m_block[$varname]))
					{
						$this->implodeBlock($varname);
					}
				}
				return(isset($this->m_varvals[$varname]) ? $this->m_varvals[$varname] : '');

			}
			else
			{
				reset($varname);
				while(list($k, $v) = each($varname))
				{
					if (!isset($this->m_varkeys[$varname]) or empty($this->m_varvals[$varname]))
					{
						if ($this->m_file[$v])
						{
							$this->loadfile($v);
						}
						if ($this->m_block[$v])
						{
							$this->implodeBlock($v);
						}
					}
					$result[$v] = $this->m_varvals[$v];
				}
				return $result;
			}
		}

		/** Gets undefined
		* @notes Internal Use
		*/
		function get_undefined($varname)
		{
			$str = $this->get_var($varname);
			preg_match_all("/\\{([a-zA-Z0-9_]+)\\}/", $str, $m);
			$m = $m[1];
			if (!is_array($m))
			{
				return false;
			}
			reset($m);
			while(list($k, $v) = each($m))
			{
				if (!isset($this->m_varkeys[$v]))
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

		/** Decided what to do with unknown tags
		* @notes Internal use
		* @returns Processed string
		*/
		function finish($str)
		{
			switch ($this->m_unknowns)
			{
				case 'keep':
					break;
				case 'remove':
					$str = preg_replace("/{[^ \t\r\n}]+}/", '', $str);
					break;
				case 'comment':
					$str = preg_replace("/{[^ \t\r\n}]+}/", "<!-- Template $varname: Variable \\1 undefined -->", $str);
					break;
			}
			return $str;
		}

		/** Print out template
		* @param Alias to processed template
		*/
		function p($varname)
		{
			print $this->finish($this->get_var($varname));
		}

		/** Gets results for finished
		* @notes Internal use
		* @returns processed tabs from finished
		*/
		function get($varname)
		{
			return $this->finish($this->get_var($varname));
		}

		/** Remove unwanted characters from filename
		* @notes Internal use
		* @returns Returns file name if not error has occured
		*/
		function filename($filename)
		{
			if (substr($filename, 0, 1) == "/" || preg_match("/[a-z]{1}:/i",$filename) )
			{
				if (file_exists($filename))
				{
					return $filename;
				}
				else
				{
					$this->c_error->halt("filename (absolute): $filename does not exist.",0,__LINE__);
					return false;
				}
			}
			reset($this->m_root);
			while(list($k, $v) = each($this->m_root))
			{
				$f = "$v/$filename";
				if (file_exists($f))
				{
					return $f;
				}
			}
			$this->c_error->halt("filename (relative): file $filename does not exist.",0,__LINE__);
			return false;
		}

		/** Removed unwanted characters for block name
		* @notes Internal use
		* @returns Block name
		*/
		function varname($m_varname)
		{
			return preg_quote("{".$m_varname."}");
		}

		/** Open the files
		* @notes Internal use
		* @returns Processed string
		*/
		function loadfile($varname)
		{
			if (!isset($this->m_file[$varname]))
			{
				$this->c_error->halt("loadfile: $varname is not a valid varname.",0,__LINE__);
				return false;
			}
			$filename = $this->filename($this->m_file[$varname]);

			$str = implode('', @file($filename));
			if (empty($str))
			{
				$this->c_error->halt("loadfile: While loading $varname, $filename does not exist or is empty.",0,__LINE__);
				return false;
			}

			$this->set_var($varname, $str);
			return true;
		}

		/** Implode Blocks
		* @notes Internal use
		*/
		function implodeBlock($varname)
		{
			$parent = $this->m_block[$varname]['parent'];
			$alias  = $this->m_block[$varname]['alias'];

			$str = $this->get_var($parent);

			$reg = "/<!--\\s+BEGIN $varname\\s+-->(.*)\n\s*<!--\\s+END $varname\\s+-->/sm";
			if (!preg_match_all($reg, $str, $m))
			{
				$this->c_error->halt("implodeBlock - no match for $varname variable",0,__LINE__);
			}
			else
			{
				$str = preg_replace($reg, "{"."$alias}", $str);
				$this->set_var($varname, $m[1][0]);
				$this->set_var($parent, $str);
			}
		}
	}
?>
