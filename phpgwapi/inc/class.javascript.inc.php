<?php
  /**************************************************************************\
  * phpGroupWare API - JavaScript                                            *
  * Written by Dave Hall skwashd at phpgroupware.org                         *
  * Copyright (C) 2003 Free Software Foundation Inc                          *		
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  *  This program is Free Software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

       /**
       * phpGroupWare javascript support class
       *
       * Only instanstiate this class using:
       * <code>
       *  if(!@is_object($GLOBALS['phpgw']->js))
       *  {
       *    $GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
       *  }
       * </code>
       *
       * This way a theme can see if this is a defined object and include the data,
       * while the is_object() wrapper prevents whiping out existing data held in 
       * this instance variables, primarily the $files variable.
       *
       * Note: The package arguement is the subdirectory of js - all js should live in subdirectories
       *
       * @package phpgwapi
       * @subpackage sessions
       * @abstract
       * @author Dave Hall
       * @copyright &copy; 2003 Free Software Foundation
       * @license GPL
       * @uses template
       * @link http://docs.phpgroupware.org/wiki/classJavaScript
       */
	class javascript
	{
		/**
		* @var array elements to be used for the on(Un)Load attributes of the body tag
		*/
		var $body;

		/**
		* @var array list of validated files to be included in the head section of a page
		*/
		var $files;

		/**
		* @var object used for holding an instance of the Template class
		*/
		var $t;
		
		/**
		* Constructor
		*
		* Initialize the instance variables
		*/
		function javascript()
		{
			$this->t = CreateObject('phpgwapi.Template', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir','phpgwapi'));
			//not currently used, but will be soon - I hope :)
		}

		
		/**
		* Returns the javascript required for displaying a popup message box
		*
		* @param string $msg the message to be displayed to user
		* @returns string the javascript to be used for displaying the message
		*/
		function get_alert($msg)
		{
		  return 'return alert("'.lang($msg).'");';
		}

		/**
		* Adds on(Un)Load= attributes to the body tag of a page
		*
		* @returns string the attributes to be used
		*/
		function get_body_attribs()
		{
			$js  = ($this->body['onLoad'] ? 'onLoad="' . $this->body['onLoad'] . '"' : '');
			$js .= ($this->body['onUnload'] ? 'onUnLoad="' . $this->body['onUnload'] . '"': '');
			return $js;
		}

		/**
		* Returns the javascript required for displaying a confirmation message box
		*
		* @param string $msg the message to be displayed to user
		* @returns string the javascript to be used for displaying the message
		*/
		function get_confirm($msg)
		{
			return 'return confirm("'.lang($msg).'");';
		}
		
		/**
		* Used for generating the list of external js files to be included in the head of a page
		*
		* NOTE: This method should only be called by the template class.
		* The validation is done when the file is added so we don't have to worry now
		*
		* @returns string the html needed for importing the js into a page
		*/
		function get_script_links()
		{
			$links = '';
			if(!empty($this->files) && is_array($this->files))
			{
				$links = "<!--JS Imports from phpGW javascript class -->\n";
				foreach($this->files as $app => $packages)
				{
					if(!empty($packages) && is_array($packages))
					{
						foreach($packages as $pkg => $files)
						{
							if(!empty($files) && is_array($files))
							{
								foreach($files as $file)
								{
									$links .= '<script type="text/javascript" src="'
								 	. $GLOBALS['phpgw_info']['server']['webserver_url']
								 	. "/$app/js/$pkg/$file.js".'">'
								 	. "</script>\n";
								}
							}
						}
					}
				}
			}
			return $links;
		}

		/**
		* Sets an onLoad action for a page
		*
		* @param string javascript to be used
		*/
		function set_onload($code)
		{
			$this->body['onLoad'] = $code;
		}

		/**
		* Sets an onUnload action for a page
		*
		* @param string javascript to be used
		*/
		function set_onunload($code)
		{
			$this->body['onUnload'] = $code;
		}

		/**
		* DO NOT USE - NOT SURE IF I AM GOING TO USE IT - ALSO IT NEEDS SOME CHANGES!!!!
		* Used for removing a file or package of files to be included in the head section of a page
		*
		* @param string $app application to use
		* @param string $package the name of the package to be removed
		* @param string $file the name of a file in the package to be removed - if ommitted package is removed
		*/
		function unset_script_link($app, $package, $file=False)
		{
			/* THIS DOES NOTHING ATM :P
			if($file !== False)
			{
				unset($this->files[$app][$package][$file]);
			}
			else
			{
				unset($this->files[$app][$package]);
			}
			*/
		}

		/**
		* Checks to make sure a valid package and file name is provided
		*
		* @param string $package package to be included
		* @param string $file file to be included - no ".js" on the end
		* @param string $app application directory to search - default = phpgwapi
		* @returns bool was the file found?
		*/
		function validate_file($package, $file, $app='phpgwapi')
		{
			if(is_readable(PHPGW_INCLUDE_ROOT . "/$app/js/" . $package .'/'. $file . '.js'))
			{
				$this->files[$app][$package][$file] = $file;
				return True;
			}
			elseif($app != 'phpgwapi')
			{
				if(is_readable(PHPGW_INCLUDE_ROOT . '/phpgwapi/js/' . $package .'/'. $file . '.js'))
				{
					$this->files['phpgwapi'][$package][$file] = $file;
					return True;
				}
				return False;
			}
		}
	}
?>
