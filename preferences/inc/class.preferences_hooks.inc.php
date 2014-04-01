<?php
/**
 * EGroupware - Preferences hooks
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package preferences
 * @version $Id$
 */

/**
 * Static hooks for preferences class
 */
class preferences_hooks
{
	/**
	 * Hook return common preferences settings
	 *
	 * @param string|array $hook_data
	 * @return array
	 */
	static public function settings($hook_data)
	{
		$navbar_format = array(
			'icons'          => lang('Icons only'),
			'icons_and_text' => lang('Icons and text'),
			'text'           => lang('Text only')
		);

		$link_list_format = array(
			'icons'          => lang('Icons only'),
			'icons_and_text' => lang('Icons and text'),
			'text'           => lang('Text only')
		);

		if (!$hook_data['setup'])
		{
			$langs = translation::get_installed_langs();

			$tzs = egw_time::getTimezones();
		}

		$date_formats = array(
			'd.m.Y' => 'd.m.Y',
			'Y-m-d' => 'Y-m-d',
			'm/d/Y' => 'm/d/Y',
			'm-d-Y' => 'm-d-Y',
			'm.d.Y' => 'm.d.Y',
			'Y/d/m' => 'Y/d/m',
			'Y-d-m' => 'Y-d-m',
			'Y.d.m' => 'Y.d.m',
			'Y/m/d' => 'Y/m/d',
			'Y.m.d' => 'Y.m.d',
			'd/m/Y' => 'd/m/Y',
			'd-m-Y' => 'd-m-Y',
			'd-M-Y' => 'd-M-Y'
		);

		$time_formats = array(
			'12' => lang('12 hour'),
			'24' => lang('24 hour')
		);

		$html_enter_mode = array(
			'p'		=> lang('p: Paragraph'),
			'div'	=> lang('div'),
			'br'	=> lang('br')
		);

		$rich_text_editor_skins = array(
			'kama'    => lang ('Default theme'),
			'moono'	=> lang('Moono theme'),
			'moonocolor'	=> lang('Moono color theme'),
		);

		$account_sels = array(
			'selectbox'     => lang('Selectbox'),
			'primary_group' => lang('Selectbox with primary group and search'),
			'popup'         => lang('Popup with search'),
			'groupmembers'  => lang('Selectbox with groupmembers'),
			'none'          => lang('No user-selection at all'),
		);

		$account_display = array(
			'firstname' => lang('Firstname'). ' '.lang('Lastname'),
			'lastname'  => lang('Lastname').', '.lang('Firstname'),
			'username'  => lang('username'),
			'firstall'  => lang('Firstname').' '.lang('Lastname').' ['.lang('username').']',
			'lastall'   => lang('Lastname').', '.lang('Firstname').' ['.lang('username').']',
			'allfirst'  => '['.lang('username').'] '.lang('Firstname').' '.lang('Lastname'),
			'all'       => '['.lang('username').'] '.lang('Lastname').','.lang('Firstname'),
		);

		if ($hook_data['setup'])	// called via setup
		{
			$lang = get_var('ConfigLang',Array('POST','COOKIE'),'en');
			list(,$country) = explode('-',$lang);
			if (empty($country)) $country = $lang;
		}
		// check for old rte_font_size pref including px and split it in size and unit
		if (!isset($GLOBALS['egw_setup']) &&
			substr($GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'], -2) == 'px')
		{
			$prefs = $GLOBALS['egw']->preferences;
			foreach(array('user','default','forced') as $type)
			{
				if (substr($prefs->{$type}['common']['rte_font_size'], -2) == 'px')
				{
					egw_ckeditor_config::font_size_from_prefs($prefs->{$type}, $prefs->{$type}['common']['rte_font_size'],
						$prefs->{$type}['common']['rte_font_unit']);
					$prefs->save_repository(false, $type);
				}
			}
			egw_ckeditor_config::font_size_from_prefs($GLOBALS['egw_info']['user']['preferences'],
				$GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'],
				$GLOBALS['egw_info']['user']['preferences']['common']['rte_font_unit']);
		}
		// Settings array for this app
		$settings = array(
			array(
				'type'	=> 'section',
				'title'	=> 'Look & feel'
			),
			'maxmatchs' => array(
				'type'  => 'input',
				'label' => 'Max matches per page',
				'name'  => 'maxmatchs',
				'help'  => 'Any listing in eGW will show you this number of entries or lines per page.<br>To many slow down the page display, to less will cost you the overview.',
				'size'  => 3,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => 20,
			),
			'template_set' => array(
				'type'   => 'select',
				'label'  => 'Interface/Template Selection',
				'name'   => 'template_set',
				'values' => egw_framework::list_templates(),
				'help'   => 'A template defines the layout of eGroupWare and it contains icons for each application.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'idots',
			),
			'theme' => array(
				'type'   => 'select',
				'label'  => 'Theme (colors/fonts) Selection',
				'name'   => 'theme',
				'values' => !$hook_data['setup'] ? $GLOBALS['egw']->framework->list_themes() : array(),
				'help'   => 'A theme defines the colors and fonts used by the template.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'idots',
			),
			'navbar_format' => array(
				'type'   => 'select',
				'label'  => 'Show navigation bar as',
				'name'   => 'navbar_format',
				'values' => $navbar_format,
				'help'   => 'You can show the applications as icons only, icons with app-name or both.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'icons_and_text',
			),
			'link_list_format' => array(
				'type'		=>	'select',
				'label'		=>	'Show links between eGroupWare aps as',
				'name'		=>	'link_list_format',
				'values'	=>	$link_list_format,
				'help'		=>	'You can show the linked entries with icons only, icons with app-name or both.',
				'xmlrpc'	=>	True,
				'admin'		=>	False,
				'forced'    =>  'icons',
			),
			'link_list_thumbnail' => array(
				'type'		=>	'select',
				'label'		=>	'Display thumbnails for linked images',
				'name'		=>	'link_list_thumbnail',
				'values'    =>  array(
					'1'		=>  lang('Yes'),
					'0'     =>	lang('No'),
				),
				'help'		=>	'Images linked to an entry can be displayed as thumbnails.  You can turn this off to speed up page display.',
				'xmlrpc'	=>	True,
				'admin'		=>	False,
				'forced'    =>  '1',
			),
			'select_mode' => array(
				'type'		=>	'select',
				'label'		=>	'Select additional lines in lists by',
				'name'		=>	'select_mode',
				'values'    =>  array(
					'EGW_SELECTMODE_DEFAULT' => lang('holding Ctrl/Cmd key and click on the line'),
					'EGW_SELECTMODE_TOGGLE'  => lang('just clicking on the line, like a checkbox'),
				),
				'help'		=>	'If a line is already selected, further lines get either selected by holding Ctrl/Cmd key and clicking on them (to not unselect the current selected line), or by just clicking on them as for a checkbox. If no line is selected clicking on one allways selects it. Holding down Shift key selects everything between current select line and the one clicked.',
				'xmlrpc'	=>	True,
				'admin'		=>	False,
				'default'    =>  'EGW_SELECTMODE_DEFAULT',
			),
			'account_selection' => array(
				'type'   => 'select',
				'label'  => 'How do you like to select accounts',
				'name'   => 'account_selection',
				'values' => $account_sels,
				'help'   => lang('The selectbox shows all available users (can be very slow on big installs with many users). The popup can search users by name or group.').' '.
					lang('The two last options limit the visibility of other users. Therefore they should be forced and apply NOT to administrators.'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'primary_group'
			),
			'account_display' => array(
				'type'   => 'select',
				'label'  => 'How do you like to display accounts',
				'name'   => 'account_display',
				'values' => $account_display,
				'help'   => 'Set this to your convenience. For security reasons, you might not want to show your Loginname in public.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'lastname',
			),
			'show_currentusers' => array(
				'type'  => 'check',
				'label' => 'Show number of current users',
				'name'  => 'show_currentusers',
				'help'  => 'Should the number of active sessions be displayed for you all the time.',
				'xmlrpc' => False,
				'admin'  => True,
				'forced' => true,
			),
			array(
				'type'	=> 'section',
				'title'	=> 'Formatting & general settings'
			),
			'lang' => array(
				'type'   => 'select',
				'label'  => 'Language',
				'name'   => 'lang',
				'values' => $langs,
				'help'   => 'Select the language of texts and messages within eGroupWare.<br>Some languages may not contain all messages, in that case you will see an english message.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> $lang,
			),
			'country' => array(
				'type'   => 'select',
				'label'  => 'Country',
				'name'   => 'country',
				'values' => ExecMethod('phpgwapi.country.countries'),
				'help'   => 'In which country are you. This is used to set certain defaults for you.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> strtoupper($country),
			),
			'tz' => array(
				'type'   => 'select',
				'label'  => 'Time zone',
				'name'   => 'tz',
				'values' => $tzs,
				'help'   => 'Please select your timezone.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> date_default_timezone_get(),
			),
			'tz_selection' => array(
				'type'   => 'multiselect',
				'label'  => 'Permanent time zone selection',
				'name'   => 'tz_selection',
				'values' => $tzs ? call_user_func_array('array_merge',$tzs) : null,	// only flat arrays supported
				'help'   => 'Please select timezones, you want to be able to quickly switch between. Switch is NOT shown, if less then two are selected.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => date_default_timezone_get(),
			),
			'dateformat' => array(
				'type'   => 'select',
				'label'  => 'Date format',
				'name'   => 'dateformat',
				'values' => $date_formats,
				'help'   => 'How should eGroupWare display dates for you.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> $lang == 'en' ? 'Y/m/d' : 'd.m.Y',
			),
			'timeformat' => array(
				'type'   => 'select',
				'label'  => 'Time format',
				'name'   => 'timeformat',
				'values' => $time_formats,
				'help'   => 'Do you prefer a 24 hour time format, or a 12 hour one with am/pm attached.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 24,
			),
			'number_format' => array(
				'type'   => 'select',
				'label'  => 'Number format',
				'name'   => 'number_format',
				'values' => array(
					'.'  => '1234.56',
					','  => '1234,56',
					'.,' => '1,234.56',
					',.' => '1.234,56',
					'. ' => '1 234.56',
					', ' => '1 234,56',
				),
				'help'   => 'Thousands separator is only used for displaying and not for editing numbers.',
				'xmlrpc' => True,
				'admin'  => false,
				'default'=> '.',
			),
			'currency' => array(
				'type'  => 'input',
				'label' => 'Currency',
				'name'  => 'currency',
				'help'  => 'Which currency symbol or name should be used in eGroupWare.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> $lang == 'en' ? '$' : 'EUR',
			),
			'csv_charset' => array(
				'type'   => 'select',
				'label'  => 'Charset for the CSV export/import',
				'name'   => 'csv_charset',
				'values' => translation::get_installed_charsets(),
				'help'   => 'Which charset should be used for the CSV export. The system default is the charset of this eGroupWare installation.',
				'xmlrpc' => True,
				'admin'  => false,
				'default'=> 'iso-8859-1',
			),
			array(
				'type'	=> 'section',
				'title'	=> 'Text editor settings'
			),
			'rte_font' => array(
				'type'   => 'select',
				'label'  => 'Default font',
				'name'   => 'rte_font',
				'values' => egw_ckeditor_config::$font_options,
				'help'   => 'Automatically start with this font',
				'xmlrpc' => True,
				'admin'  => false
			),
			'rte_font_unit' => array(
				'type'   => 'select',
				'label'  => 'Font size unit',
				'name'   => 'rte_font_unit',
				'values' => array_map('lang', egw_ckeditor_config::$font_unit_options),
				'help'   => 'Unit of displayed font sizes: either "px" as used eg. for web-pages or "pt" as used in text processing.',
				'default'=> 'pt',
				'xmlrpc' => True,
				'admin'  => false
			),
			'rte_font_size' => array(
				'type'   => 'select',
				'label'  => 'Default font size',
				'name'   => 'rte_font_size',
				'values' => egw_ckeditor_config::$font_size_options,
				'help'   => 'Automatically start with this font size',
				'xmlrpc' => True,
				'admin'  => false
			),
			'spellchecker_lang' => array(
				'type'   => 'select',
				'label'  => 'Spellchecker language',
				'name'   => 'spellchecker_lang',
				'values' => $langs,
				'help'   => 'Select the language of the spellchecker integrated into the rich text editor.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> $lang,
			),
			'rte_enter_mode' => array(
				'type'   => 'select',
				'label'  => 'Rich text editor enter mode',
				'name'   => 'rte_enter_mode',
				'values' => $html_enter_mode,
				'help'   => 'Select how the rich text editor will generate the enter (linebreak) tag.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'br',
			),
			'rte_skin' => array(
				'type'   => 'select',
				'label'  => 'Rich text editor theme',
				'name'   => 'rte_skin',
				'values' => $rich_text_editor_skins,
				'help'   => 'Select the theme (visualization) of the rich text editor.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'office2003',
			),
			'rte_features' => array(
				'type'   => 'select',
				'label'  => 'Features of the editor',
				'name'   => 'rte_features',
				'values' => array('simple'=>'simple','extended'=>'regular','advanced'=>'everything'),
				'help'   => 'How many toolbar buttons are available',
				'admin'  => false,
				'default'=> 'extended'
			),
		);
		// disable thumbnails, if no size configured by admin
		if (!$GLOBALS['egw_info']['server']['link_list_thumbnail']) unset($settings['link_list_thumbnail']);

		return $settings;
	}

	/**
	 * Hook called when a user gets deleted, to delete his preferences
	 *
	 * @param string|array $data
	 */
	public static function deleteaccount($data)
	{
		$account_id = (int)$data['account_id'];

		if($account_id > 0)	// user
		{
			$GLOBALS['egw']->preferences->delete_user($account_id);
		}
		elseif ($account_id < 0)	// group
		{
			$GLOBALS['egw']->preferences->delete_group($account_id);
		}
	}

	/**
	 * Preferences link to Admin >> Edit user
	 */
	public static function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description'   => 'Preferences',
			'url'           => '/index.php',
			'extradata'     => 'menuaction=preferences.preferences_settings.index',
			'popup'         => '900x450',
		);
	}

	/**
	 * hooks to build sidebox-menu plus the admin and preferences sections
	 *
	 * @param string|array $args hook args
	 */
	static function admin($args)
	{
		unset($args);	// unused, but required by function signature
		$appname = 'preferences';
		$file = Array(
			'Site configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname),
		);
		display_section($appname, $file);
	}

	/**
	 * Hook returning options for deny_acl groups
	 *
	 * @param array $config
	 */
	public static function deny_prefs($config)
	{
		$accountsel = new uiaccountsel();

		return '<input type="hidden" value="" name="newsettings[deny_prefs]" />'.
			$accountsel->selection('newsettings[deny_prefs]', 'deny_prefs', $config['deny_prefs'], 'groups', 4);
	}

	/**
	 * Hook returning options for deny_acl groups
	 *
	 * @param array $config
	 */
	public static function deny_acl($config)
	{
		$accountsel = new uiaccountsel();

		return '<input type="hidden" value="" name="newsettings[deny_acl]" />'.
			$accountsel->selection('newsettings[deny_acl]', 'deny_acl', $config['deny_acl'], 'groups', 4);
	}

	/**
	 * Hook returning options for deny_cats groups
	 *
	 * @param array $config
	 */
	public static function deny_cats($config)
	{
		$accountsel = new uiaccountsel();

		return '<input type="hidden" value="" name="newsettings[deny_cats]" />'.
			$accountsel->selection('newsettings[deny_cats]', 'deny_cats', $config['deny_cats'], 'groups', 4);
	}
}
