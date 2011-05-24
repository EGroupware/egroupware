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
			'office2003' => lang ('Office 2003 theme'),
			'v2'     => lang ('Silver theme')
		);

		$user_apps = array();
		foreach((array)$GLOBALS['egw_info']['user']['apps'] as $app => $data)
		{
			if($GLOBALS['egw_info']['apps'][$app]['status'] != 2 && $app)
			{
				$user_apps[$app] = $GLOBALS['egw_info']['apps'][$app]['title'] ? $GLOBALS['egw_info']['apps'][$app]['title'] : lang($app);
			}
		}

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
		// Settings array for this app
		$settings = array(
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
				'values' => isset($GLOBALS['egw']->framework) ? $GLOBALS['egw']->framework->list_themes() : array(),
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
			'show_currentusers' => array(
				'type'  => 'check',
				'label' => 'Show number of current users',
				'name'  => 'show_currentusers',
				'help'  => 'Should the number of active sessions be displayed for you all the time.',
				'xmlrpc' => False,
				'admin'  => True,
				'forced' => true,
			),
			'default_app' => array(
				'type'   => 'select',
				'label'  => 'Default application',
				'name'   => 'default_app',
				'values' => $user_apps,
				'help'   => "The default application will be started when you enter eGroupWare or click on the homepage icon.<br>You can also have more than one application showing up on the homepage, if you don't choose a specific application here (has to be configured in the preferences of each application).",
				'xmlrpc' => False,
				'admin'  => False,
				'default'=> '',
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
			'show_help' => array(
				'type'   => 'check',
				'label'  => 'Show helpmessages by default',
				'name'   => 'show_help',
				'help'   => 'Should this help messages shown up always, when you enter the preferences or only on request.',
				'xmlrpc' => False,
				'admin'  => False,
				'default'=> True,
			),
			'enable_dragdrop' => array(
				'type'   => 'check',
				'label'  => 'Enable drag and drop functionality (experimental)',
				'name'   => 'enable_dragdrop',
				'help'   => 'Enables or disables drag and drop functions in all applications. If the browser does not support '.
					    'drag and drop, it will be disabled automatically. This feature is experimental at the moment.',
				'xmlrpc' => False,
				'admin'  => False,
				'forced' => true,
			),
            'enable_ie_dropdownmenuhack' => array(
                'type'   => 'check',
                'label'  => 'Enable selectbox dropdown resizing for IE (experimental)',
                'name'   => 'enable_ie_dropdownmenuhack',
                'help'   => 'Enables or disables selectbox dropdown resizing for IE in all applications. If the browser is not an IE '.
                        ', the option will not apply. This feature is experimental at the moment.',
                'xmlrpc' => False,
                'admin'  => False,
                'forced' => false,
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
		);
		// disable thumbnails, if no size configured by admin
		if (!$GLOBALS['egw_info']['server']['link_list_thumbnail']) unset($settings['link_list_thumbnail']);

		return $settings;
	}

	/**
	 * Hook to return preferences menu items
	 *
	 * @param string|array $hook_data
	 */
	public static function preferences($hook_data)
	{
		if (!$GLOBALS['egw']->acl->check('nopasswordchange',1))
		{
			$file['Change your Password'] = $GLOBALS['egw']->link('/index.php','menuaction=preferences.uipassword.change');
		}
		$file['common preferences'] = $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname=preferences');

		display_section('preferences',$file);
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
}
