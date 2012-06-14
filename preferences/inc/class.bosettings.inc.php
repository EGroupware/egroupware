<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class bosettings
	{
		var $xmlrpc = False;
		var $session_data = array();
		var $settings = array();
		var $prefs = array();
		var $appname = '';
		var $debug = False;

		var $public_functions = array(
			'read' => True,
			'process_array' => True
		);

		var $xml_functions  = array();
		var $xmlrpc_methods = array();
		var $soap_functions = array(
			'read' => array(
				'in'  => array('int','int','struct','string','int'),
				'out' => array('array')
			),
			'process_array' => array(
				'in'  => array('int','struct'),
				'out' => array('array')
			),
			'list_methods' => array(
				'in' => array('string'),
				'out' => array('struct')
			)
		);

		function bosettings($appname='')
		{
			$this->xmlrpc = @is_object($GLOBALS['server']) && $GLOBALS['server']->last_method;
			$this->session_data = $GLOBALS['egw']->session->appsession('session_data','preferences');

			$this->appname = $appname;
		}

		function save_session($appname,$type,$show_help,$prefix,$notifies='',$referer='')
		{
			$GLOBALS['egw']->session->appsession('session_data','preferences',array(
				'type'      => $type,	// save our state in the app-session
				'show_help' => $show_help,
				'prefix'    => $prefix,
				'appname'   => $appname,		// we use this to reset prefix on appname-change
				'notifies'  => $notifies,
				'referer'   => $referer ? $referer : $this->session_data['referer'],
			));
		}

		function call_hook($appname)
		{
			$this->appname = $appname;

			translation::add_app($this->appname);
			if($this->appname != 'preferences')
			{
				translation::add_app('preferences');	// we need the prefs translations too
			}

			// calling app specific settings hook
			$settings = $GLOBALS['egw']->hooks->single('settings',$this->appname);
			// it either returns the settings or save it in $GLOBALS['settings'] (deprecated!)
			if (isset($settings) && is_array($settings) && $settings)
			{
				$this->settings = array_merge($this->settings,$settings);
			}
			elseif(isset($GLOBALS['settings']) && is_array($GLOBALS['settings']) && $GLOBALS['settings'])
			{
				$this->settings = array_merge($this->settings,$GLOBALS['settings']);
			}
			else
			{
				return False;	// no settings returned
			}

			// calling settings hook all apps can answer (for a specific app)
			foreach($GLOBALS['egw']->hooks->process('settings_'.$this->appname,$this->appname,true) as $app => $settings)
			{
				if (isset($settings) && is_array($settings) && $settings)
				{
					$this->settings = array_merge($this->settings,$settings);
				}
			}
			/* Remove ui-only settings */
			if($this->xmlrpc)
			{
				foreach($this->settings as $key => $valarray)
				{
					if(!$valarray['xmlrpc'])
					{
						unset($this->settings[$key]);
					}
				}
			}
			else
			{
				/* Here we include the settings hook file for the current template, if it exists.
					 This is not handled by the hooks class and is only valid if not using xml-rpc.
				 */
				$tmpl_settings = EGW_SERVER_ROOT.$GLOBALS['egw']->framework->template_dir.'/hook_settings.inc.php';
				if($this->appname == 'preferences' && file_exists($tmpl_settings))
				{
					include($tmpl_settings);
					$this->settings = array_merge($this->settings,$GLOBALS['settings']);
				}
			}
			// check if we have a default/forced value from the settings hook,
			// which is NOT stored as default currently
			// --> store it as default, to allow to propagate defaults to existing installations
			if ($appname == 'preferences') $appname = 'common';
			foreach ($this->settings as $name => $data)
			{
				if ((string)$GLOBALS['egw']->preferences->default[$appname][$name] === '' &&
					((string)$data['default'] !== '' || (string)$data['forced'] !== ''))
				{
					$default = (string)$data['forced'] !== '' ? $data['forced'] : $data['default'];
					//echo "<p>".__METHOD__."($appname) $this->appname/$appname/$name=$default NOT yet set!</p>\n";
					$GLOBALS['egw']->preferences->default[$appname][$name] = $default;
					$need_update = true;
				}
			}
			if ($need_update)
			{
				$GLOBALS['egw']->preferences->save_repository(false,'default',true);
			}
			if($this->debug)
			{
				_debug_array($this->settings);
			}
			return True;
		}

		function read($app,$prefix='',$type='user')
		{
			switch($type)	// set up some class vars to be used when processing the hooks
			{
				case 'forced':
					$this->prefs = &$GLOBALS['egw']->preferences->forced[$this->check_app()];
					break;
				case 'default':
					$this->prefs = &$GLOBALS['egw']->preferences->default[$this->check_app()];
					break;
				default:
					$this->prefs = &$GLOBALS['egw']->preferences->user[$this->check_app()];
					// use prefix if given in the url, used for email extra-accounts
					if($prefix != '')
					{
						$prefix_arr = explode('/',$prefix);
						foreach($prefix_arr as $pre)
						{
							$this->prefs = &$this->prefs[$pre];
						}
					}
			}
			if($this->debug)
			{
				echo 'Preferences array: ' . $app . "\n";
				_debug_array($this->prefs);
			}
			/* Ensure that a struct will be returned via xml-rpc (this might change) */
			if($this->xmlrpc)
			{
				return $this->prefs;
			}
			else
			{
				return False;
			}
		}

		function _write($appname,$prefix,$type='user')
		{
		}

		/**
		 * Verify and save preferences
		 *
		 * @param array &$repository
		 * @param array $array
		 * @param array $notifies
		 * @param string $type
		 * @param boolean $only_verify=false
		 * @param string $prefix='' deprecated
		 * @return string with verification error or null on success
		 */
		function process_array(&$repository, $array, $notifies, $type, $only_verify=false, $prefix='')
		{
			//_debug_array($repository);
			$appname = $this->check_app();
			$prefs = &$repository[$appname];

			if($prefix != '')
			{
				// AFAIK this is NOT used anymore, was used in old email app
				throw egw_exception_assertion_failed(__METHOD__."(,,$notifies, $type, prefix='$prefix') prefix!=''!");
				$prefix_arr = explode('/',$prefix);
				foreach($prefix_arr as $pre)
				{
					$prefs = &$prefs[$pre];
				}
			}
			unset($prefs['']);
			//_debug_array($array);exit;
			while(is_array($array) && list($var,$value) = each($array))
			{
				if(isset($value) && $value !== '' && $value !== '**NULL**')
				{
					if (get_magic_quotes_gpc())
					{
						$value = is_array($value) ? array_map('stripslashes',$value) : stripslashes($value);
					}
					if (is_array($value))
					{
						if (isset($value['pw']))
						{
							$value = $value['pw'];
							if(empty($value))
							{
								continue;	// dont write empty password-fields
							}
						}
						elseif(isset($value[$vfs_type='vfs_file']) || isset($value[$vfs_type='vfs_dir']) || isset($value[$vfs_type='vfs_dirs']))
						{
							$value = $value[$vfs_type];
							if ($value === '')
							{
								// empty is always allowed
							}
							elseif ($vfs_type == 'vfs_file')
							{
								if ($value[0] != '/' || !egw_vfs::stat($value))
								{
									$error = lang('%1 is no existing vfs file!',htmlspecialchars($value));
								}
							}
							else
							{
								// split multiple comma or whitespace separated directories
								// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
								foreach($vfs_type == 'vfs_dir' ? array($value) : preg_split('/[,\s]+\//', $value) as $n => $dir)
								{
									if ($n) $dir = '/'.$dir;	// re-adding trailing slash removed by split
									if ($dir[0] != '/' || !egw_vfs::stat($dir) || !egw_vfs::is_dir($dir))
									{
										$error .= ($error ? ' ' : '').lang('%1 is no existing vfs directory!',$dir);
									}
								}
							}
						}
						else
						{
							$value = implode(',',$value);	// multiselect
						}
					}
					$prefs[$var] = $value;

					if(is_array($notifies) && isset($notifies[$var]))	// need to translate the key-words back
					{
						$prefs[$var] = $GLOBALS['egw']->preferences->lang_notify($prefs[$var],$notifies[$var],True);
					}
					// need to call preferences::add, to also set affective prefs!
					if (!$only_verify) $GLOBALS['egw']->preferences->add($appname, $var, $prefs[$var], $type);
				}
				else
				{
					unset($prefs[$var]);
					// need to call preferences::delete, to also set affective prefs!
					if (!$only_verify) $GLOBALS['egw']->preferences->delete($appname, $var, $type);
				}
			}
			//echo "prefix='$prefix', prefs=<pre>"; print_r($repository[$appname]); echo "</pre>\n";

			// the following hook can be used to verify the prefs
			// if you return something else than False, it is treated as an error-msg and
			// displayed to the user (the prefs are not saved)
			//
			if(($error .= $GLOBALS['egw']->hooks->single(array(
				'location' => 'verify_settings',
				'prefs'    => $repository[$appname],
				'prefix'   => $prefix,
				'type'     => $type
				),
				$appname
			)))
			{
				return $error;
			}

			if (!$only_verify) $GLOBALS['egw']->preferences->save_repository(True,$type);

			// certain common prefs (language, template, ...) require the session to be re-created
			if ($appname == 'common' && !$only_verify)
			{
				egw::invalidate_session_cache();
			}

			return null;
		}

		function check_app()
		{
			if($this->appname == 'preferences')
			{
				return 'common';
			}
			else
			{
				return $this->appname;
			}
		}

		/* TODO these need work and may change without notice.  Please remove this line when this is settled. */
		function list_methods($_type='xmlrpc')
		{
			/*
				This handles introspection or discovery by the logged in client,
				in which case the input might be an array.  The server always calls
				this function to fill the server dispatch map using a string.
			*/
			if(is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'read' => array(
							'function'  => 'read',
							'signature' => array(array(xmlrpcStruct,xmlrpcString,xmlrpcString,xmlrpcString)),
							'docstring' => lang('Read prefs for the specified application.')
						),
						'write' => array(
							'function'  => 'process_array',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct,xmlrpcStruct,xmlrpcStruct,xmlrpcString,xmlrpcString)),
							'docstring' => lang('Write prefs for the specified application.')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}
	}
