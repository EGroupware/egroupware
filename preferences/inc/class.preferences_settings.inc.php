<?php
/**
 * EGroupware: Preferences app UI for settings/preferences
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package preferences
 * @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

/**
 * UI for settings / Api\Preferences
 */
class preferences_settings
{
	/**
	 * Methods callable via menuaction
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
	);
	/**
	 * App we work on
	 * @var string
	 */
	public $appname = 'preferences';
	/**
	 * Preferences read by call_hook
	 * @var array
	 */
	public $settings = array();

	/**
	 * Edit preferences
	 *
	 * @param array $content =null
	 * @param string $msg =''
	 */
	function index(array $content=null, $msg='')
	{
		$tpl = new Etemplate('preferences.settings');
		if (!is_array($content))
		{
			$appname = isset($_GET['appname']) && $_GET['appname'] != 'preferences' &&
				isset($GLOBALS['egw_info']['user']['apps'][$_GET['appname']]) ? $_GET['appname'] : 'common';
			$type = 'user';
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			if ($GLOBALS['egw_info']['user']['apps']['admin'] &&
				isset($_GET['account_id']) && (int)$_GET['account_id'] &&
				$GLOBALS['egw']->accounts->exists((int)$_GET['account_id']))
			{
				$account_id = (int)$_GET['account_id'];
				$type = $_GET['account_id'] < 0 ? 'group' : 'user';
			}
			$content['current_app'] = isset($GLOBALS['egw_info']['user']['apps'][$_GET['current_app']]) ? $_GET['current_app'] : $appname;
		}
		else
		{
			//error_log(__METHOD__."(".array2string($content).")");
			if ($content['button'])
			{
				list($button) = each($content['button']);
				$appname = $content['old_appname'] ? $content['old_appname'] : 'common';
				switch($button)
				{
					case 'save':
					case 'apply':
						// check if user has rights to store preferences for $type and $account_id
						if ($content['old_type'] !== 'user' && !$GLOBALS['egw_info']['user']['apps']['admin'])
						{
							throw new Api\Exception\NoPermission\Admin;
						}
						list($type,$account_id) = explode(':', $content['old_type']);
						// merge prefs of all tabs together again
						$prefs = array();
						foreach($content as $name => $val)
						{
							if (is_array($val) && strpos($name, 'tab') === 0)
							{
								$prefs = array_merge($prefs, $val);
							}
						}
						//error_log(__METHOD__."() button=$button, content=".array2string($content).' --> prefs='.array2string($prefs));;;
						if ($account_id && $account_id != $GLOBALS['egw']->preferences->get_account_id())
						{
							$GLOBALS['egw']->preferences->set_account_id($account_id);
							$GLOBALS['egw']->preferences->read_repository();
						}
						// name of common preferences which require reload of framework, if there values change
						$require_reload = array('template_set', 'theme', 'lang', 'template_color', 'template_custom_color');
						$old_values = array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'], array_flip($require_reload));

						$attribute = $type == 'group' ? 'user' : $type;
						if (!($msg=$this->process_array($GLOBALS['egw']->preferences->$attribute, $prefs, $content['types'], $appname, $attribute)))
						{
							$msg_type = 'success';
							$msg = lang('Preferences saved.');

							// do we need to reload whole framework
							if ($appname == 'common')
							{
								if ($account_id && $GLOBALS['egw']->preferences->get_account_id() != $GLOBALS['egw_info']['user']['account_id'])
								{
									$GLOBALS['egw']->preferences->set_account_id($GLOBALS['egw_info']['user']['account_id']);
								}
								$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
								$new_values = array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'], array_flip($require_reload));
								//error_log(__METHOD__."() ".__LINE__.": old_values=".array2string($old_values).", new_values=".array2string($new_values));
								if ($old_values != $new_values)
								{
									Framework::refresh_opener($msg, null, null, null, null, null, null, $msg_type);
								}
							}
						}
						// update client-side Api\Preferences in response
						Framework::ajax_get_preference($appname);
				}
				if (in_array($button, array('save','cancel')))
				{
					Api\Json\Response::get()->call('egw.message', $msg, $msg_type);
					Framework::window_close();
				}
			}
			$appname = $content['appname'] ? $content['appname'] : 'common';
			list($type,$account_id) = explode(':', $content['type']);
			//_debug_array($prefs);
		}
		if ($account_id && $account_id != $GLOBALS['egw']->preferences->get_account_id())
		{
			$GLOBALS['egw']->preferences->set_account_id($account_id);
			$GLOBALS['egw']->preferences->read_repository();
		}
		$preserve = array('types' => array());
		// preserv open tab, if appname is not chanaged
		if (!isset($content['old_appname']) || $content['old_appname'] == $content['appname'] ||
			$content['old_appname'] == 'common' && !$content['appname'])
		{
			$old_tab = $content['tabs'];
		}
		// we need to run under calling app, to be able to restore it to it's index page after
		$preserve['current_app'] = $content['current_app'];
		$GLOBALS['egw_info']['flags']['currentapp'] = $content['current_app'] == 'common' ?
			'preferences' : $content['current_app'];
		Framework::includeCSS('preferences','app');

		// if not just saved, call validation before, to be able to show failed validation of current prefs
		if (!isset($button))
		{
			$attribute = $type == 'group' ? 'user' : $type;
			$msg = $this->process_array($GLOBALS['egw']->preferences->$attribute,
				(array)$GLOBALS['egw']->preferences->{$attribute}[$appname], $preserve['types'], $appname, $attribute, true);
		}

		$sel_options = $readonlys = null;
		$data = $this->get_content($appname, $type, $sel_options, $readonlys, $preserve['types'], $tpl);
		$preserve['appname'] = $preserve['old_appname'] = $data['appname'];
		$preserve['type'] = $preserve['old_type'] = $data['type'];
		if (isset($old_tab)) $data['tabs'] = $old_tab;

		if ($msg) Framework::message($msg, $msg_type ? $msg_type : 'error');

		$tpl->exec('preferences.preferences_settings.index', $data, $sel_options, $readonlys, $preserve, 2);
	}

	/**
	 * Verify and save preferences
	 *
	 * @param array &$repository values get updated here
	 * @param array $values new values
	 * @param array $types setting-name => type
	 * @param string $appname appname or 'common'
	 * @param string $type 'user', 'default', 'forced'
	 * @param boolean $only_verify =false
	 * @return string with verification error or null on success
	 */
	function process_array(array &$repository, array $values, array $types, $appname, $type, $only_verify=false)
	{
		//_debug_array($repository);
		$prefs = &$repository[$appname];

		unset($prefs['']);
		//_debug_array($values);exit;
		foreach($values as $var => $value)
		{
			// type specific validation
			switch((string)$types[$var])
			{
				case 'password':	// dont write empty password-fields
					if (empty($value)) continue 2;
					break;
				case 'vfs_file':
				case 'vfs_dir':
				case 'vfs_dirs':
					if ($value === '')
					{
						// empty is always allowed

						// If forced, empty == not set
						if($type == 'forced')
						{
							unset($prefs[$var]);
							// need to call preferences::delete, to also set affective prefs!
							if (!$only_verify) $GLOBALS['egw']->preferences->delete($appname, $var, $type);
							continue 2;
						}
					}
					elseif ($types[$var] == 'vfs_file')
					{
						if ($value[0] != '/' || !Vfs::stat($value) || Vfs::is_dir($value))
						{
							$error = lang('%1 is no existing vfs file!',htmlspecialchars($value));
						}
					}
					else
					{
						// split multiple comma or whitespace separated directories
						// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
						foreach($types[$var] == 'vfs_dir' ? array($value) : preg_split('/[,\s]+\//', $value) as $n => $dir)
						{
							if ($n) $dir = '/'.$dir;	// re-adding trailing slash removed by split
							if ($dir[0] != '/' || !Vfs::stat($dir) || !Vfs::is_dir($dir))
							{
								$error .= ($error ? ' ' : '').lang('%1 is no existing vfs directory!',$dir);
							}
						}
					}
					break;
				case 'Array':	// notify
					$value = $GLOBALS['egw']->preferences->lang_notify($value, $types[$var], True);
					break;
			}

			if (isset($value) && $value !== '' && $value !== '**NULL**' && $value !== array())
			{
				if (is_array($value)) $value = implode(',',$value);	// multiselect

				$prefs[$var] = $value;

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

		// the following hook can be used to verify the prefs
		// if you return something else than False, it is treated as an error-msg and
		// displayed to the user (the prefs are not saved)
		//
		if(($error .= Api\Hooks::single(array(
				'location' => 'verify_settings',
				'prefs'    => &$repository[$appname],
				'type'     => $type,
				'preprocess' => $only_verify,
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
			Egw::invalidate_session_cache();
		}

		return null;
	}

	/**
	 * Get content, sel_options and readonlys for given appname and type
	 *
	 * @param string $appname appname or 'common'
	 * @param string $type
	 * @param array &$sel_options
	 * @param array &$readonlys
	 * @param array &$types on return setting-name => setting-type
	 * @param etemplate $tpl
	 * @throws Api\Exception\WrongParameter
	 * @return array content
	 */
	function get_content($appname, $type, &$sel_options, &$readonlys, &$types, $tpl)
	{
		if (!$this->call_hook($appname, $type, $GLOBALS['egw']->preferences->get_account_id()))
		{
			throw new Api\Exception\WrongParameter("Could not find settings for application: ".$appname);
		}
		$attribute = $type == 'group' ? 'user' : $type;
		//error_log(__METHOD__."('$appname', '$type' ) attribute='$attribute', preferences->account_id=".$GLOBALS['egw']->preferences->get_account_id());

		//_debug_array($this->settings); exit;
		$sel_options = $readonlys = $content = $tabs = array();
		// disable all but first tab and name current tab "tab1", for apps not using sections
		$tab = 'tab1';
		foreach($this->settings as $setting)
		{
			if (!is_array($setting)) continue;
			if ($type != 'forced' && (string)$GLOBALS['egw']->preferences->forced[$appname][$setting['name']] !== '')
			{
				continue;	// forced preferences are not displayed, unless we edit them
			}
			$types[$setting['name']] = $old_type = $setting['type'];

			switch($old_type)
			{
				case 'section':
					$tab = 'tab'.(1+count($tabs));
					$tabs[] = array(
						'id' => $tab,
						'template' => 'preferences.settings.tab1',
						'label' => $setting['title'],
					);
					// fall through
				case 'subsection':	// is in old code, but never seen it used
					continue 2;

				case 'notify':
					$vars = $GLOBALS['egw']->preferences->vars;
					if (is_array($setting['values'])) $vars += $setting['values'];
					$GLOBALS['egw']->preferences->{$attribute}[$appname][$setting['name']] =
						$GLOBALS['egw']->preferences->lang_notify($GLOBALS['egw']->preferences->{$attribute}[$appname][$setting['name']], $vars);
					$types[$setting['name']] = $vars;	// store vars for re-translation, instead type "notify"
					if ($setting['help'] && ($setting['run_lang'] || !isset($setting['run_lang'])))
					{
						$setting['help'] = lang($setting['help']);
					}
					$setting['help'] .= '<p><b>'.lang('Substitutions and their meanings:').'</b>';
					foreach($vars as $var => $var_help)
					{
						$lname = ($lname = lang($var)) == $var.'*' ? $var : $lname;
						$setting['help'] .= "<br>\n".'<b>$$'.$lname.'$$</b>: '.$var_help;
					}
					$setting['help'] .= "</p>\n";
					$setting['run_lang'] = false;	// already done now
					// handle as textarea
				case 'textarea':
					$setting['type'] = is_a($tpl, 'etemplate') ? 'textarea' : 'textbox';
					$tpl->setElementAttribute($tab.'['.$setting['name'].']', 'multiline', 'true');
					$tpl->setElementAttribute($tab. '[' . $setting['name'] . ']','width', '99%' );
					// anyway setting via css: width: 99%, height: 5em
					// for old eT use size attribute
					if (is_a($tpl, 'etemplate') && (!empty($setting['cols']) || !empty($setting['rows'])))
					{
						$setting['size'] = $setting['rows'].','.$setting['cols'];
					}
					break;
				case 'password':
				case 'vfs_file':
				case 'vfs_dir':
				case 'vfs_dirs':
				case 'input':
					$setting['type'] = 'textbox';
					break;
				case 'check':
					$setting['type'] = 'select';
					$setting['values'] = array('1' => lang('yes'), '0' => lang('no'));
					break;
				case 'multiselect':
					$setting['type'] = 'select';
					$tpl->setElementAttribute($tab.'['.$setting['name'].']', 'rows', 5);
					if (!isset($setting['size'])) $setting['size'] = '5';	// old eT
					break;
				case 'color':
					$setting['type'] = 'colorpicker';
					break;
				case 'date-duration':
					if (!isset($setting['size'])) $setting['size'] = 'm,dhm,24,1';
					$attrs = explode(',', $setting['size']);
					foreach(array("data_format","display_format", "hours_per_day", "empty_not_0", "short_labels") as $n => $name)
					{
						if ((string)$attrs[$n] !== '') $tpl->setElementAttribute($tab.'['.$setting['name'].']', $name, $attrs[$n]);
					}
					break;
			}
			// move values/options to sel_options array
			if (isset($setting['values']) && is_array($setting['values']))
			{
				if ($old_type != 'multiselect' && $old_type != 'notify')
				{
					switch($type)
					{
						case 'user':
							$setting['values'] = array('' => lang('Use default'))+$setting['values'];
							break;
						case 'default':
						case 'group':
							$setting['values'] = array('' => lang('No default'))+$setting['values'];
							break;
						case 'forced';
							$setting['values'] = array('**NULL**' => lang('Users choice'))+$setting['values'];
							break;
					}
				}
				$sel_options[$setting['name']] = $setting['values'];
			}
			if ($type == 'user')
			{
				$default = $GLOBALS['egw']->preferences->group[$appname][$setting['name']] ?
					$GLOBALS['egw']->preferences->group[$appname][$setting['name']] :
					$GLOBALS['egw']->preferences->default[$appname][$setting['name']];

				// replace default value(s) for selectboxes with selectbox labels
				if (isset($setting['values']) && is_array($setting['values']))
				{
					$default = self::get_default_label($default, $setting['values']);
				}
				if (is_array($types[$setting['name']]))	// translate the substitution names
				{
					$default = $GLOBALS['egw']->preferences->lang_notify($default, $types[$setting['name']]);
				}
			}
			if ($setting['help'] && ($setting['run_lang'] || !isset($setting['run_lang'])))
			{
				$setting['help'] = lang($setting['help']);
			}
			$content[$tab][] = array(
				'name' => $setting['name'],
				'type' => $setting['type'],
				'label' => preg_replace('|<br[ /]*>|i', "\n", $setting['label']),
				'help' => lang($setting['help']),	// is html
				'default' => !empty($default) ? lang('Default').': '.$default : null,
				'onchange' => $setting['onchange'],
			);
			//error_log("appname=$appname, attribute=$attribute, setting=".array2string($setting));
			$content[$tab][$setting['name']] = $GLOBALS['egw']->preferences->{$attribute}[$appname][$setting['name']];
			//if ($old_type == 'multiselect') $content[$tab][$setting['name']] = explode(',', $content[$tab][$setting['name']]);
		}
		// defining used tabs on run-time
		if ($tabs)
		{
			$tpl->setElementAttribute('tabs', 'tabs', $tabs);
		}
		else
		{
			// Modifications are kept in the request, so reset to just one
			$tpl->setElementAttribute('tabs', 'tabs', array(array(
				'id' => 'tab1',
				'template' => 'preferences.settings.tab1',
				'label' => 'general settings'
			)));
		}

		$content['appname'] = $appname;
		$sel_options['appname'] = array();
		foreach(Api\Hooks::implemented('settings') as $app)
		{
			if ($app != 'preferences' && $GLOBALS['egw_info']['user']['apps'][$app])
			{
				$sel_options['appname'][$app] = $GLOBALS['egw_info']['apps'][$app]['title'];
			}
		}
		natcasesort($sel_options['appname']);

		$sel_options['type'] = array(
			'user' => 'Your preferences',
			'default' => 'Default preferences',
			'forced' => 'Forced preferences',
		);
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$content['type'] = $type;
			if (($id = $GLOBALS['egw']->preferences->get_account_id()) != $GLOBALS['egw_info']['user']['account_id'])
			{
				$content['type'] .= ':'.$id;
				$sel_options['type'][$content['type']] = Api\Accounts::username($GLOBALS['egw']->preferences->account_id);

				// Restrict app list to apps the user has access to
				$user_apps = $GLOBALS['egw']->acl->get_user_applications($id);
				$sel_options['appname'] = array_intersect_key($sel_options['appname'], $user_apps);
			}
			foreach($GLOBALS['egw']->accounts->search(array('type' => 'groups', 'order' => 'account_lid')) as $account_id => $group)
			{
				$sel_options['type']['group:'.$account_id] = lang('Preferences').' '.Api\Accounts::format_username($group['account_lid'], '', '', $account_id);
			}
		}
		else
		{
			$content['type'] = 'user';
			$readonlys['type'] = true;
		}
		//_debug_array($content); exit;
		//_debug_array($sel_options); //exit;
		return $content;
	}

	/**
	 * Get label for given default value(s)
	 *
	 * @param string|array $default default value(s) to get label for
	 * @param array $values values optional including optgroups
	 * @param boolean $lang =true
	 * @return string comma-separated and translated labels
	 */
	protected static function get_default_label($default, array $values, $lang=true)
	{
		// explode comma-separated multiple default values
		if (!is_array($default) && !isset($values[$default]) && strpos($default, ',') !== false)
		{
			$labels = explode(',', $default);
		}
		else
		{
			$labels = (array)$default;
		}
		foreach($labels as &$def)
		{
			if (isset($values[$def]))
			{
				$def = is_array($values[$def]) ? $values[$def]['label'] : $values[$def];
			}
			else	// value could be in an optgroup
			{
				foreach($values as $value)
				{
					if (is_array($value) && !isset($value['label']) && isset($value[$def]))
					{
						$def = is_array($value[$def]) ? $value[$def]['label'] : $value[$def];
						break;
					}
				}
			}
			if ($lang) $def = lang($def);
		}
		$label = implode(', ', $labels);
		//error_log(__METHOD__."(".array2string($default).', '.array2string($values).") returning $label");
		return $label;
	}

	/**
	 * Get preferences by calling various hooks to supply them
	 *
	 * Sets $this->appname and $this->settings
	 *
	 * @param string $appname appname or 'common'
	 * @param string $type ='user' 'default', 'forced', 'user' or 'group'
	 * @param int|string $account_id =null account_id for user or group prefs, or "forced" or "default"
	 * @return boolean
	 */
	protected function call_hook($appname, $type='user', $account_id=null)
	{
		$this->appname = $appname == 'common' ? 'preferences' : $appname;

		// Set framework here to make sure we get the right settings for user's [newly] selected template
		$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw']->preferences->data['common']['template_set'];
		Api\Translation::add_app($this->appname);
		if($this->appname != 'preferences')
		{
			Api\Translation::add_app('preferences');	// we need the prefs translations too
		}

		// make type available, to hooks from Egw\Applications can use it, eg. activesync
		$hook_data = array(
			'location' => 'settings',
			'type' => $type,
			'account_id' => $account_id,
		);
		$GLOBALS['type'] = $type;	// old global variable

		// calling app specific settings hook
		$settings = Api\Hooks::single($hook_data, $this->appname);
		// it either returns the settings or save it in $GLOBALS['settings'] (deprecated!)
		if (isset($settings) && is_array($settings) && $settings)
		{
			$this->settings = array_merge($this->settings, $settings);
		}
		elseif(isset($GLOBALS['settings']) && is_array($GLOBALS['settings']) && $GLOBALS['settings'])
		{
			$this->settings = array_merge($this->settings, $GLOBALS['settings']);
		}
		else
		{
			return False;	// no settings returned
		}

		// calling settings hook all apps can answer (for a specific app)
		$hook_data['location'] = 'settings_'.$this->appname;
		foreach(Api\Hooks::process($hook_data, $this->appname,true) as $settings)
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
		foreach ($this->settings as $name => $data)
		{
			// only set not yet set default prefs, so user is able to unset it again with ""
			// (only works with type vfs_*, other types delete empty values!)
			if (!isset($GLOBALS['egw']->preferences->default[$appname][$name]) &&
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
}
