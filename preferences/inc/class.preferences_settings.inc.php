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
		$tpl = new etemplate_new('preferences.settings');
		if (!is_array($content))
		{
			$appname = isset($_GET['appname']) ? $_GET['appname'] : 'preferences';
			if (!$this->call_hook($appname))
			{
				throw new egw_exception_wrong_parameter("Could not find settings for application: ".$_GET['appname']);
			}
			//_debug_array($this->settings); exit;
			$sel_options = $readonlys = $content = $tabs = array();
			// disable all but first tab and name current tab "tab1", for apps not using sections
			$tab = 'tab1';
			$readonlys['tabs'] = array(
				'tab2' => true,
				'tab3' => true,
				'tab4' => true,
				'tab5' => true,
			);

			foreach($this->settings as $setting)
			{
				if (!is_array($setting)) continue;
				switch($setting['type'])
				{
					case 'section':
						$tabs[] = $setting['title'];
						$tab = 'tab'.count($tabs);
						$tpl->setElementAttribute($tab, 'label', $setting['title']);
						$readonlys['tabs'][$tab] = false;
						// fall through
					case 'subsection':	// is in old code, but never seen it used
						continue 2;

					case 'input':
						$setting['type'] = 'textbox';
						break;
					case 'check':
						$setting['type'] = 'select';
						$setting['values'] = array('no', 'yes');
						break;
					case 'multiselect':
						$setting['type'] = 'select';
						break;
					case 'color':
						$setting['type'] = 'colorpicker';
						break;
				}
				// move values/options to sel_options array
				if (isset($setting['values']) && is_array($setting['values']))
				{
					// need to call fix_encoded_options manually, as id is not matching because of autorepeat
					etemplate_widget_menupopup::fix_encoded_options($setting['values']);
					$sel_options[$tab][count($content[$tab]).'[value]'] = $setting['values'];
					unset($setting['values']);
				}
				$content[$tab][] = $setting;
			}
			//_debug_array($content); exit;
			//_debug_array($sel_options); exit;
		}
		else
		{
			$this->appname = $content['appname'];
		}
		$tpl->exec('preferences.preferences_settings.index', $content, $sel_options, $readonlys, $content);
	}

	/**
	 * Get preferences by calling various hooks to supply them
	 *
	 * Sets $this->appname and $this->settings
	 *
	 * @param string $appname
	 * @return boolean
	 */
	protected function call_hook($appname)
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
