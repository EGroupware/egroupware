<?php
	/**************************************************************************\
	* eGroupWare API - JavaScript                                              *
	* Written by Dave Hall skwashd at phpgroupware.org                         *
	* Copyright (C) 2003 Free Software Foundation Inc                          *		
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            * 
	* ------------------------------------------------------------------------ *
	*  This program is Free Software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */


	/**
	 * eGroupWare javascript support class
	 *
	 * Only instanstiate this class using:
	 * <code>
	 *  if(!@is_object($GLOBALS['egw']->js))
	 *  {
	 *    $GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
	 *  }
	 * </code>
	 *
	 * This way a theme can see if this is a defined object and include the data,
	 * while the is_object() wrapper prevents whiping out existing data held in 
	 * this instance variables, primarily the $files variable.
	 *
	 * Note: The package argument is the subdirectory of js - all js should live in subdirectories
	 *
	 * @package phpgwapi
	 * @subpackage sessions
	 * @abstract
	 * @author Dave Hall
	 * @copyright &copy; 2003 Free Software Foundation
	 * @license GPL
	 * @uses template
	 *
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
		* @var boolean Load JS API ?
		*/
		var $js_api;
		
		/**
		* Constructor
		*
		* Initialize the instance variables
		*/
		function javascript()
		{
			//$this->t =& CreateObject('phpgwapi.Template', ExecMethod('phpgwapi.phpgw.common.get_tpl_dir','phpgwapi'));
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
			$js = '';
			foreach(array('onLoad','onUnload','onResize') as $what)
			{
				if (!empty($this->body[$what]))
				{
					$js .= ' '.$what.'="' . str_replace(array('\\\'','"','\\','&#39;'),array('&#39;','\\"','\\\\','\\\''),$this->body[$what]) . '"';
				}
			}
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
			$links  = "<!--JS Imports from phpGW javascript class -->\n";
			if(!empty($this->files) && is_array($this->files))
			{
				foreach($this->files as $app => $packages)
				{
					if(!empty($packages) && is_array($packages))
					{
						foreach($packages as $pkg => $files)
						{
							if(!empty($files) && is_array($files))
							{
								foreach($files as $file => $browser)
								{
									$pkg = $pkg == '.' ? '' : $pkg.'/';
									$browser = $browser == '.' ? '' : $browser.'/';
									
									$f = "/$app/js/$pkg$browser$file" . '.js?'. filectime(EGW_INCLUDE_ROOT."/$app/js/$pkg$browser$file.js") .'">';
									$links .= '<script type="text/javascript" src="'. $GLOBALS['egw_info']['server']['webserver_url']. $f. "</script>\n";
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
			$this->body['onLoad'] .= $code;
		}

		/**
		* Sets an onUnload action for a page
		*
		* @param string javascript to be used
		*/
		function set_onunload($code)
		{
			$this->body['onUnload'] .= $code;
		}

		/**
		* Sets an onResize action for a page
		*
		* @param string javascript to be used
		*/
		function set_onresize($code)
		{
			$this->body['onResize'] .= $code;
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
		* @param bool   $browser insert specific browser javascript.
			*
		* @discuss The browser specific option loads the file which is in the correct
		*          browser folder. Supported folder are those supported by class.browser.inc.php
		*
		* @returns bool was the file found?
		*/
		function validate_file($package, $file, $app='phpgwapi', $browser=true)
		{
			if ($browser)
			{
				$browser_folder = html::$user_agent;
			}
			else
			{
				$browser_folder = '.'; 
			}
			
			if ($this->included_files[$app][$package][$file]) return true;

			if(is_readable(EGW_INCLUDE_ROOT ."/$app/js/$package/$browser_folder/$file.js"))
			{
				$this->files[$app][$package][$file] = $browser_folder;
				return True;
			}
			elseif (is_readable(EGW_INCLUDE_ROOT. "/$app/js/$package/$file.js"))
			{
				$this->files[$app][$package][$file] = '.';
				return True;
			}
			elseif($app != 'phpgwapi')
			{
				if(is_readable(EGW_INCLUDE_ROOT ."/phpgwapi/js/$package/$browser_folder/$file.js"))
				{
					$this->files['phpgwapi'][$package][$file] = $browser_folder;
					return True;
				}
				elseif(is_readable(EGW_INCLUDE_ROOT ."phpgwapi/js/$package/$file.js"))
				{
					$this->files['phpgwapi'][$package][$file] = '.';
					return True;
				}
				return False;
			}
		}

		function validate_jsapi()
		{
			if (EGW_UNCOMPRESSED_THYAPI)
			{
				$this->validate_file('plugins', 'thyPlugins');
			}

			/* This was included together with javascript globals to garantee prior load of dynapi. But it doesn't seems
			 * right to me... maybe on class common, it should load dynapi before everything... */
			$this->validate_file('dynapi','dynapi');

			// Initialize DynAPI
			$GLOBALS['egw_info']['flags']['java_script'] .= '<script language="javascript">'."\n";
			$GLOBALS['egw_info']['flags']['java_script'] .= "dynapi.library.setPath(GLOBALS['serverRoot']+'/phpgwapi/js/dynapi/')\n";
			$GLOBALS['egw_info']['flags']['java_script'] .= "dynapi.library.include('dynapi.library')\n";
			$GLOBALS['egw_info']['flags']['java_script'] .= "dynapi.library.include('dynapi.api')\n";
			$GLOBALS['egw_info']['flags']['java_script'] .= "</script>\n";/**/

			//FIXME: These files are temporary! They should be included inside DynAPI or substituted by
			// other ones
			$this->validate_file('jsapi', 'jsapi');
			$this->validate_file('wz_dragdrop', 'wz_dragdrop');
			$this->validate_file('dJSWin', 'dJSWin');
			$this->validate_file('dTabs', 'dTabs');
			$this->validate_file('connector', 'connector');
			$this->validate_file('xmlrpcMsgCreator','xmlrpc');
			$this->validate_file('jsolait','init');
			return true;
		}
		
		function get_javascript_globals()
		{
			/* Default Global Messages */
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['parseError'] = lang('Failed to Contact Server or Invalid Response from Server. Try to relogin. Contact Admin in case of faliure.');
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['serverTimeout'] = lang('Could not contact server. Operation Timed Out!');
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['dataSourceStartup'] = lang('Starting Up...');
			
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['connector_1'] = lang('Contacting Server...');
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['connector_2'] = lang('Server Contacted. Waiting for response...');
			$GLOBALS['egw_info']['flags']['java_script_globals']['messages']['jsapi']['connector_3'] = lang('Server answered. Processing response...');
			$GLOBALS['egw_info']['flags']['java_script_globals']['preferences']['common'] =& $GLOBALS['egw_info']['user']['preferences']['common'];

			/* Default Global API Variables */
			$browser = strtolower(ExecMethod('phpgwapi.browser.get_agent'));
			switch ($browser)
			{
				case 'ie':
				case 'opera':
					$thyapi_comp = 'thyapi_comp_'.$browser.'.js';
					break;
				default:
					$thyapi_comp = 'thyapi_comp_gecko.js';
			}

			$GLOBALS['egw_info']['flags']['java_script_globals']['jsapi']['imgDir'] = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/images';
			if (EGW_UNCOMPRESSED_THYAPI)
			{
				$jsCode  = "<!-- JS Global Variables and ThyAPI Insertion -->\n" .
							 '<script type="text/javascript" src="'.($GLOBALS['egw_info']['server']['webserver_url'] ? $GLOBALS['egw_info']['server']['webserver_url'].'/' : '/').
							 '/phpgwapi/js/dynapi/dynapi.js"></script>'."\n".
							 '<script language="javascript">'."\n".
							 'var GLOBALS = new Object();'."\n" .
							 "GLOBALS['serverRoot'] = '".($GLOBALS['egw_info']['server']['webserver_url'] ? $GLOBALS['egw_info']['server']['webserver_url'].'/' : '/')."';\n".
							 "GLOBALS['appname'] = '".$GLOBALS['egw_info']['flags']['currentapp']."';\n".

							 "GLOBALS['httpProtocol'] = '".($_SERVER['HTTPS'] ? 'https://' : 'http://')."';\n";
			}
			else
			{
				$jsCode  = "<!-- JS Global Variables and ThyAPI Insertion -->\n" .
							 '<script type="text/javascript" src="'.($GLOBALS['egw_info']['server']['webserver_url'] ? $GLOBALS['egw_info']['server']['webserver_url'].'/' : '/').
							 '/phpgwapi/js/'.$thyapi_comp.'"></script>'."\n".
							 '<script language="javascript">'."\n".
							 'var GLOBALS = new Object();'."\n" .
							 "GLOBALS['serverRoot'] = '".($GLOBALS['egw_info']['server']['webserver_url'] ? $GLOBALS['egw_info']['server']['webserver_url'].'/' : '/')."';\n".
							 "GLOBALS['appname'] = '".$GLOBALS['egw_info']['flags']['currentapp']."';\n".

							 "GLOBALS['httpProtocol'] = '".($_SERVER['HTTPS'] ? 'https://' : 'http://')."';\n";
			}

			if ($GLOBALS['egw_info']['extra_get_vars'])
			{
				$GLOBALS['egw_info']['flags']['java_script_globals']['extra_get_vars'] = $GLOBALS['egw_info']['extra_get_vars'];
			}

			$jsCode .= $this->convert_phparray_jsarray("GLOBALS", $GLOBALS['egw_info']['flags']['java_script_globals'], false);
				
			if (EGW_UNCOMPRESSED_THYAPI)
			{
				$jsCode .= "\ndynapi.library.setPath(GLOBALS['serverRoot']+'/phpgwapi/js/dynapi/');\n".
									 "dynapi.library.include('dynapi.library');\n".
									 "dynapi.library.include('dynapi.api');\n\n";
			}

			// Enable Debug?
			$config =& CreateObject('phpgwapi.config', 'phpgwapi');
			$config_values = $config->read_repository();
			
			if ($config_values['js_debug'])
			{
				$jsCode .= "if (dynapi.ua.gecko) dynapi.library.include('dynapi.debug')\n";
			}
			
			$jsCode .= '</script>'."\n";
	
			return $jsCode;
		}

		function convert_phparray_jsarray($name, $array, $new=true)
		{
			if (!is_array($array))
			{
				return '';
			}
			
			if ($new)
			{
				$jsCode = "$name = new Object();\n";
			}
			else
			{
				$jsCode = '';
			}

			foreach ($array as $index => $value)
			{
				if (is_array($value))
				{
					$jsCode .= $name."['".$index."'] = new Object();\n";
					$jsCode .= $this->convert_phparray_jsarray($name."['".$index."']", $value,false);
					continue;
				}

				switch(gettype($value))
				{
					case 'string':
						$value = "'".str_replace(array("\n","\r"),'\n',addslashes($value))."'";
						break;

					case 'boolean':
						if ($value)
						{
							$value = 'true';
						}
						else
						{
							$value = 'false';
						}
						break;

					default:
						$value = 'null';
				}
				
				$jsCode .= $name."['".$index."'] = ".$value.";\n";
			}

			return $jsCode;
		}
	}
?>
