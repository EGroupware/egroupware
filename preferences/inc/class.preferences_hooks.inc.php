<?php
/**
 * EGroupware - Preferences hooks
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package preferences
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;

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
			$langs = Api\Translation::get_installed_langs();

			$tz_list = Api\DateTime::getTimezones();

			// Format for select
			$format = function ($key, $value) use (&$format, &$tzs)
			{
				if(is_array($value))
				{
					$value = [
						'label' => $key,
						'value' => array_map($format, array_keys($value), array_values($value))
					];
				}
				else
				{
					$value = ['label' => $value, 'value' => $key];
				}
				return $value;
			};
			$tzs = array_map($format, array_keys($tz_list), array_values($tz_list));
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

		$account_sels = array(
			'selectbox'     => lang('Selectbox'),
			'primary_group' => lang('Selectbox with primary group and search'),
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
			'firstgroup'=> lang('Firstname').' '.lang('Lastname').' ('.lang('primary group').')',
			'lastgroup' => lang('Lastname').', '.lang('Firstname').' ('.lang('primary group').')',
			'firstinital' => lang('Firstname').' '.lang('Initial'),
			'firstid'   => lang('Firstname').' ['.lang('ID').']',
		);

		if ($hook_data['setup'])	// called via setup
		{
			$lang = setup::get_lang();
		}
		if (empty($lang)) $lang = 'en';
		list(,$country) = explode('-',$lang);
		if (empty($country) && class_exists('Locale')) $country = Locale::getRegion(Locale::getDefault());
		if (empty($country)) $country = 'de';

		// check for old rte_font_size pref including px and split it in size and unit
		if (!isset($GLOBALS['egw_setup']) &&
			substr($GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'], -2) == 'px')
		{
			$prefs = $GLOBALS['egw']->preferences;
			foreach(array('user','default','forced') as $type)
			{
				if (substr($prefs->{$type}['common']['rte_font_size'], -2) == 'px')
				{
					Api\Etemplate\Widget\HtmlArea::font_size_from_prefs($prefs->{$type}, $prefs->{$type}['common']['rte_font_size'],
						$prefs->{$type}['common']['rte_font_unit']);
					$prefs->save_repository(false, $type);
				}
			}
			Api\Etemplate\Widget\HtmlArea::font_size_from_prefs($GLOBALS['egw_info']['user']['preferences'],
				$GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'],
				$GLOBALS['egw_info']['user']['preferences']['common']['rte_font_unit']);
		}

		if (!$GLOBALS['egw_info']['user']['preferences']['common']['rte_toolbar'])
		{
			$GLOBALS['egw']->preferences->add('common', 'rte_toolbar', 'fontselect,fontsizeselect,bold,italic,forecolor,backcolor,'.
				'alignleft,aligncenter,alignright,alignjustify,numlist,bullist'.
				',outdent,indent,link,image', 'user');
			$GLOBALS['egw']->preferences->save_repository(true);
		}

		// do NOT query widgets from setup / installation, it fails with an exception
		$font_options = $font_unit_options = $font_size_options = [];
		if (!isset($GLOBALS['egw_setup']))
		{
			$font_options = Api\Etemplate\Widget\HtmlArea::$font_options;
			$font_unit_options = Api\Etemplate\Widget\HtmlArea::$font_unit_options;
			$font_size_options = Api\Etemplate\Widget\HtmlArea::$font_size_options;
		}


		$textsize = array (
			'10' => lang('x-small'),
			'12' => lang('small'),
			'14' => lang('standard'),
			'16' => lang('large'),
			'20' => lang('x-large')
		);

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
				'forced' => 20,	// hidden as not used in eTemplate2
			),
			'textsize' => array(
				'type' => 'select',
				'label' => 'Set content size',
				'no_lang' => true,
				'name' => 'textsize',
				'help' => lang('It sets content size (text only) according to selected size.'),
				'xmlrpc' => True,
				'values' => $textsize,
				'default' => '14',
				'admin'  => False,
				'reload' => true
			),
			'lazy-update' => array(
				'type'   => 'select',
				'label'  => 'How to update lists',
				'name'   => 'lazy-update',
				'values' => [
					'lazy' => lang('Fast'),
					'exact' => lang('Exact'),
				],
				'help'   => 'Fast update add new entries always top of the list and updates existing ones in place, unless list is sorted by last modified. Exact updates do a full refresh, if the list is not sorted by last modified.',
				'default'=> 'lazy'
			),
			'template_set' => array(
				'type'   => 'select',
				'label'  => 'Interface/Template Selection',
				'name'   => 'template_set',
				'values' => Framework::list_templates(),
				'help'   => 'A template defines the layout of eGroupWare and it contains icons for each application.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => file_exists(EGW_SERVER_ROOT.'/pixelegg') ? 'pixelegg' : 'idots',
				'reload' => true
			),
			'theme' => array(
				'type'   => 'select',
				'label'  => 'Theme (colors/fonts) Selection',
				'name'   => 'theme',
				'values' => !$hook_data['setup'] ? $GLOBALS['egw']->framework->list_themes() : array(),
				'help'   => 'A theme defines the colors and fonts used by the template.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => file_exists(EGW_SERVER_ROOT.'/pixelegg') ? 'pixelegg' : 'idots',
				'reload' => true
			),
			'darkmode' => array(
				'type'   => 'select',
				'label'  => 'Dark mode theme',
				'name'   => 'darkmode',
				'values' => array('0' => 'off', '1' => 'on', '2'=> 'auto'),
				'help'   => 'Dark mode theme',
				'admin'  => False,
				'default' => '0'
			),
			'audio_effect'=> array(
				'type'   => 'select',
				'label'  => 'Audio effect',
				'name'   => 'audio_effect',
				'values' => array('0'=>lang('Disable'),'1'=>lang('Enable')),
				'help'   => 'Audio effect enables|disables sound effects used in the theme',
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '0',
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
				'default'=> 'selectbox'
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
			'scroll_area'=> array(
				'type'   => 'select',
				'label'  => 'Applications list scroll area',
				'name'   => 'scroll_area',
				'values' => array('0'=>lang('Disable'),'1'=>lang('Enable')),
				'help'   => 'Make applications list scrollable with up/down scroll buttons (usefull for users working with mouse with no scrollwheel)',
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '0',
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
				'reload' => true
			),
			'country' => array(
				'type'       => 'et2-select-country',
				'label'      => 'Country',
				'name'       => 'country',
				'help'       => 'In which country are you. This is used to set certain defaults for you.',
				'xmlrpc'     => True,
				'admin'      => False,
				'values'     => array(),
				'default'    => strtoupper($country),
				'attributes' => array(
					'tags' => true
				)
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
				'attributes' => array(
					'search' => true
				)
			),
			'tz_selection' => array(
				'type'   => 'multiselect',
				'label'  => 'Permanent time zone selection',
				'name'   => 'tz_selection',
				'values' => $tzs,
				'help'   => 'Please select timezones, you want to be able to quickly switch between. Switch is NOT shown, if less then two are selected.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => date_default_timezone_get(),
				'attributes' => array(
					'search' => true
				)
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
				'values' => Api\Translation::get_installed_charsets(),
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
				'values' => $font_options,
				'help'   => 'Automatically start with this font',
				'xmlrpc' => True,
				'admin'  => false,
				'default' => 'arial, helvetica, sans-serif'
			),
			'rte_font_unit' => array(
				'type'   => 'select',
				'label'  => 'Font size unit',
				'name'   => 'rte_font_unit',
				'values' => array_map('lang', $font_unit_options),
				'help'   => 'Unit of displayed font sizes: either "px" as used eg. for web-pages or "pt" as used in text processing.',
				'default'=> 'pt',
				'xmlrpc' => True,
				'admin'  => false,
				'default' => 'pt'
			),
			'rte_font_size' => array(
				'type'   => 'select',
				'label'  => 'Default font size',
				'name'   => 'rte_font_size',
				'values' => $font_size_options,
				'help'   => 'Automatically start with this font size',
				'xmlrpc' => True,
				'admin'  => false,
				'default' => '10'
			),
			'rte_formatblock' => array(
				'type'   => 'select',
				'label'  => 'Default format block',
				'name'   => 'rte_formatblock',
				'values' => array(
					'p' => lang("Paragraph"),
					'h1' => lang("Heading %1", '1'),
					'h2' => lang("Heading %1", '2'),
					'h3' => lang("Heading %1", '3'),
					'h4' => lang("Heading %1", '4'),
					'h5' => lang("Heading %1", '5'),
					'h6' => lang("Heading %1", '6'),
					'pre' => lang("Preformatted"),
					'customparagraph' => lang("Small Paragraph")
				),
				'help'   => 'Automatically start with this format block. Small Paragraph adds less line space between new lines.',
				'xmlrpc' => True,
				'admin'  => false,
				'default' => 'p'
			),
			'rte_menubar' => array(
				'type'   => 'select',
				'label'  => 'Enable menubar',
				'name'   => 'rte_menubar',
				'values' => array(
					'1' => lang('Yes'),
					'0' => lang('No'),
				),
				'help'   => 'Enable/Disable menubar from top of the editor.',
				'xmlrpc' => True,
				'admin'  => '1',
				'default' => '1',
			),
			'rte_toolbar' => array(
				'type'   => 'taglist',
				'label'  => 'Enabled features in toolbar',
				'name'   => 'rte_toolbar',
				'values' => Api\Etemplate\Widget\HtmlArea::get_toolbar_as_selOptions(),
				'help'   => 'You may select features to be enabled in toolbar. Selecting any of the tools from here means seleted "Feature of the editor" preference would be ignored.',
				'admin'  => true
			)
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
			'popup'         => '1200x600',
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
			'Site configuration' => Egw::link('/index.php','menuaction=admin.admin_config.index&appname=' . $appname.'&ajax=true'),
		);
		display_section($appname, $file);
	}
}