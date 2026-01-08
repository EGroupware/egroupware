<?php

namespace EGroupware\Kdots;
class Hooks
{
	public static function common_preferences()
	{
		$app_list_style = [
			'icons' => lang("app chooser icons"),
			'text'  => lang("app chooser icons + text"),
		];
		$keep_nm = [
			"none"    => lang("Unchanged"),
			"replace" => lang("Clean"),
		];
		$popups_options = [
			'popup_window' => lang('Popup Window'),
			'same_window'  => lang('Same Window'),
		];
		$apps = $no_navbar_apps = array();
		if (empty($hook_data['setup']) && !isset($GLOBALS['egw_setup']))    // does not work on setup time
		{
			foreach (ExecMethod('kdots.kdots_framework.navbar_apps') as $app => $data)
			{
				if (!$data['noNavbar'])
				{
					$apps[$app] = $data['title'];
				}
				else
				{
					$no_navbar_apps[$app] = $data['title'];
				}
			}
			$apps += $no_navbar_apps;
			unset($app);
			unset($data);
		}

		return array(
			'prefssection'   => array(
				'type'    => 'section',
				'title'   => lang('Preferences for the %1 template set', $GLOBALS['egw_info']['apps']['kdots']['title']),
				'no_lang' => true,
				'xmlrpc'  => False,
				'admin'   => False,
			),
			'app_chooser_style' => [
				'type'   => 'select',
				'label'  => lang('Application chooser style'),
				'name'   => 'app_chooser_style',
				'values' => $app_list_style,
				'help'   => lang("should the app chooser contain only icons or also the application name e.g. if users are not familiar with the icons")
			],
			'keep_nm_header' => [
				'type'    => 'select',
				'label'   => 'Entry list headers',
				'name'    => 'keep_nm_header',
				'values'  => $keep_nm,
				'help'    => '',
				'xmlrpc'  => false,
				'admin'   => false,
				'default' => 'replace',
			],
			'open_popups_in' => [
				'type'   => 'select',
				'label'  => 'Open popups in',
				'name'   => 'open_popups_in',
				'values' => $popups_options
			],
			/* disabled for now
			'show_generation_time' => array(
				'type'   => 'check',
				'label'  => 'Show page generation time',
				'name'   => 'show_generation_time',
				'help'   => 'Show page generation time on the bottom of the page?',
				'xmlrpc' => False,
				'admin'  => False,
				'forced' => false,
			),*/
			'open_tabs' => array(
				'type' => 'select-tabs',
				'label' => 'Open application tabs',
				'name' => 'open_tabs',
				'values' => $no_navbar_apps,
				'help' => 'Allows to set a default or force the open application tabs.',
				'xmlrpc' => True,
				'admin' => False,
				'default' => 'addressbook,calendar,mail,filemanager,infolog,rocketchat',
			),
			'active_tab' => array(
				'type' => 'select-tab',
				'label' => 'Active application tab',
				'name' => 'active_tab',
				'values' => $no_navbar_apps,
				'help' => 'Allows to set a default or force the active application tab for new logins.',
				'xmlrpc' => True,
				'admin' => False,
				'default' => 'calendar',
			),
			'template_custom_color' => array(
				'type' => 'color',
				'label' => 'Custom color',
				'no_lang' => true,
				'name' => 'template_custom_color',
				'help' => lang('Used instead of the application color.') . "\n" . lang('Use eg. %1 or %2', '#FF0000', 'orange'),
				'xmlrpc' => True,
				'admin' => False,
			),
			/* currently not used
			'sidebox_custom_color' => array(
				'type' => 'color',
				'label' => 'Custom sidebar menu active color, defaults to above color darkened',
				'no_lang' => true,
				'name' => 'sidebox_custom_color',
				'help' => lang('Use eg. %1 or %2','#FF0000','orange'),
				'xmlrpc' => True,
				'admin'  => False,
			), */
			'loginbox_custom_color' => array(
				'type' => 'color',
				'label' => 'Custom login box color, defaults to above color darkened',
				'no_lang' => true,
				'name' => 'loginbox_custom_color',
				'help' => lang('Use eg. %1 or %2', '#FF0000', 'orange') . '.',
				'xmlrpc' => True,
				'admin' => False,
			),
			'loginbox_text_color' => array(
				'type' => 'color',
				'label' => 'Custom login box text color, defaults to white',
				'no_lang' => true,
				'name' => 'loginbox_text_color',
				'help' => lang('Use eg. %1 or %2', '#FF0000', 'orange') . '.',
				'xmlrpc' => True,
				'admin' => False,
			),
			'keep_colorful_app_icons' => array(
				'type' => 'check',
				'label' => 'Show App Icons in there app color',
				'no_lang' => true,
				'name' => 'keep_colorful_app_icons',
				'help' => lang('Activate to show more colorful app icons in the open tabs. If unchecked they default to custom color lightened'),
				'xmlrpc' => True,
				'admin' => False,
			),
			'navbar_format' => false,    // not used in KDots (defined in common prefs)
			'default_app' => false,        // not used in KDots, as we can have multiple tabs open ...
		);
	}
}