<?php
/**
 * EGroupware: Preferences app UI for settings/preferences
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package preferences
 * @copyright (c) 2013 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php';

/**
 * UI for settings / preferences
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
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index(array $content=null, $msg='')
	{
		$tpl = new etemplate_old('preferences.settings');
		if (!is_array($content))
		{
			$appname = isset($_GET['appname']) ? $_GET['appname'] : 'preferences';
			$type = 'user';
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
			if ($GLOBALS['egw_info']['user']['apps']['admin'] &&
				isset($_GET['account_id']) && (int)$_GET['account_id'] &&
				$GLOBALS['egw']->accounts->exists((int)$_GET['account_id']))
			{
				$account_id = (int)$_GET['account_id'];
				$type = $_GET['account_id'] < 0 ? 'group' : 'user';
			}
		}
		else
		{
			//_debug_array($content);
			$appname = $content['appname'] ? $content['appname'] : 'preferences';
			list($type,$account_id) = explode(':', $content['type']);
			$prefs = array_merge($content['tab1'], $content['tab2'], $content['tab3'], $content['tab4']);
			if ($content['button'])
			{
				list($button) = each($content['button']);
				switch($button)
				{
					case 'save':
					case 'apply':
						// ToDo: save preferences

						$msg = lang('Preferences saved.').array2string($prefs);
						if ($button == 'apply') break;
						// fall throught
					case 'cancel':
						egw::redirect_link('/preferences/index.php');
				}
			}
			//_debug_array($prefs);
		}
		if ($account_id && $account_id != $GLOBALS['egw']->preferences->account_id)
		{
			$GLOBALS['egw']->preferences->account_id = $account_id;
			$GLOBALS['egw']->preferences->read_repository();
		}
		$content = $this->get_content($appname, $type, $sel_options, $readonlys, $tpl);
		$content['msg'] = $msg;

		$tpl->exec('preferences.preferences_settings.index', $content, $sel_options, $readonlys, array(
			'appname' => $content['appname'],
			'type' => $content['type'],
		));
	}

	/**
	 * Get content, sel_options and readonlys for given appname and type
	 *
	 * @param string $appname
	 * @param string $type
	 * @param array &$sel_options
	 * @param array &$readonlys
	 * @param etemplate $tpl
	 * @throws egw_exception_wrong_parameter
	 * @return array content
	 */
	function get_content($appname, $type, &$sel_options, &$readonlys, $tpl)
	{
		if (!$this->call_hook($appname, $type))
		{
			throw new egw_exception_wrong_parameter("Could not find settings for application: ".$_GET['appname']);
		}
		if ($appname == 'preferences') $appname = 'common';
		$attribute = $type == 'group' ? 'user' : $type;
		//error_log(__METHOD__."('$appname', '$type' ) attribute='$attribute', preferences->account_id=".$GLOBALS['egw']->preferences->account_id);

		//_debug_array($this->settings); exit;
		$sel_options = $readonlys = $content = $tabs = array();
		// disable all but first tab and name current tab "tab1", for apps not using sections
		$tab = 'tab1';
		foreach($this->settings as $setting)
		{
			if (!is_array($setting)) continue;
			if ($type != 'forced' && !empty($GLOBALS['egw']->preferences->forced[$appname][$setting['name']]))
			{
				continue;	// forced preferences are not displayed, unless we edit them
			}
			switch($old_type = $setting['type'])
			{
				case 'section':
					$tab = 'tab'.(1+count($tabs));
					$tabs[$tab] = $setting['title'];
					$tpl->setElementAttribute($tab, 'label', $setting['title']);
					if (count($tabs) > 5)
					{
						throw new egw_exception_assertion_failed("App $appname has more then 4 preference tabs!");
					}
					// fall through
				case 'subsection':	// is in old code, but never seen it used
					continue 2;

				case 'vfs_file':
				case 'vfs_dir':
				case 'vfs_dirs':
				case 'notify':
					// ToDo: implementation ...
					// handle as input for now
				case 'input':
					$setting['type'] = 'textbox';
					if (isset($setting['size']))
					{
						$tpl->setElementAttribute($tab.'['.$setting['name'].']', 'size', $setting['size']);
					}
					break;
				case 'check':
					$setting['type'] = 'select';
					$setting['values'] = array('1' => lang('yes'), '0' => lang('no'));
					break;
				case 'multiselect':
					$setting['type'] = 'select';
					$tpl->setElementAttribute($tab.'['.$setting['name'].']', 'multiple', 5);
					if (!isset($setting['size'])) $setting['size'] = '5';	// old eT
					break;
				case 'color':
					$setting['type'] = 'colorpicker';
					break;
			}
			// move values/options to sel_options array
			if (isset($setting['values']) && is_array($setting['values']))
			{
				if ($old_type != 'multiselect')
				{
					switch($type)
					{
						case 'user':
							$setting['values'] = array('' => lang('Use default'))+$setting['values'];
							break;
						case 'forced';
							$setting['values'] = array('' => lang('Users choice'))+$setting['values'];
							break;
					}
				}
				// need to call fix_encoded_options manually, as id is not matching because of autorepeat
				etemplate_widget_menupopup::fix_encoded_options($setting['values']);
				$sel_options[$setting['name']] = $setting['values'];
			}
			if ($type == 'user')
			{
				$default = $GLOBALS['egw']->preferences->default[$appname][$setting['name']];
				if (isset($setting['values']) && (string)$setting['values'][$default] !== '')
				{
					$default = $setting['values'][$default];
				}
				elseif (strpos($default, ',') !== false)
				{
					$values = array();
					foreach(explode(',', $default) as $value)
					{
						if (isset($setting['values'][$value])) $values[] = $setting['values'][$value];
					}
					if ($values) $default = implode(', ', $values);
				}
			}
			$content[$tab][] = array(
					'name' => $setting['name'],
					'type' => $setting['type'],
					'label' => str_replace('<br>', "\n", $setting['label']),
					'help' => str_replace('<br>', "\n", $setting['help']),
					'size' => $setting['size'],	// old eT
					'default' => !empty($default) ? lang('Default').': '.$default : null,
			);
			$content[$tab][$setting['name']] = $GLOBALS['egw']->preferences->{$attribute}[$appname][$setting['name']];
			//if ($old_type == 'multiselect') $content[$tab][$setting['name']] = explode(',', $content[$tab][$setting['name']]);
		}
		// disabling not used tabs, does NOT work in new eT
		$readonlys['tabs'] = array(
			'tab2' => !isset($tabs['tab2']),
			'tab3' => !isset($tabs['tab3']),
			'tab4' => !isset($tabs['tab4']),
			'tab5' => !isset($tabs['tab5']),
		);
		$tpl->setElementAttribute('tabs', 'label', implode('|', $tabs));	// old eT

		$content['appname'] = $appname;
		$sel_options['appname'] = array();
		foreach($GLOBALS['egw']->hooks->hook_implemented('settings') as $app)
		{
			if ($app != 'preferences' && $GLOBALS['egw_info']['apps'][$app])
			{
				$sel_options['appname'][$app] = $GLOBALS['egw_info']['apps'][$app]['title'];
			}
		}
		natcasesort($sel_options['appname']);

		if ($GLOBALS['egw_info']['apps']['admin'])
		{
			$sel_options['type'] = array(
				'user' => 'Your preferences',
				'default' => 'Default preferences',
				'forced' => 'Forced preferences',
			);
			$content['type'] = $type;
			if ($GLOBALS['egw']->preferences->account_id != $GLOBALS['egw_info']['user']['account_id'])
			{
				$content['type'] .= ':'.$GLOBALS['egw']->preferences->account_id;
				$sel_options['type'][$content['type']] = common::grab_owner_name($GLOBALS['egw']->preferences->account_id);
			}
			foreach($GLOBALS['egw']->accounts->search(array('type' => 'groups', 'sort' => 'account_lid')) as $account_id => $group)
			{
				$sel_options['type']['group:'.$account_id] = common::display_fullname($group['account_lid'], '', '', $account_id);
			}
		}
		else
		{
			$content['type'] = 'user';
		}
		//_debug_array($content); exit;
		//_debug_array($sel_options); //exit;
		return $content;
	}

	/**
	 * Get preferences by calling various hooks to supply them
	 *
	 * Sets $this->appname and $this->settings
	 *
	 * @param string $appname
	 * @return boolean
	 */
	protected function call_hook($appname, $type='user')
	{
		$this->appname = $appname;

		translation::add_app($this->appname);
		if($this->appname != 'preferences')
		{
			translation::add_app('preferences');	// we need the prefs translations too
		}

		// make type available, to hooks from applications can use it, eg. activesync
		$GLOBALS['type'] = $type;

		// calling app specific settings hook
		$settings = $GLOBALS['egw']->hooks->single('settings',$this->appname);
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
