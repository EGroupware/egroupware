<?php
/**
 * EGroupware: Stylite Pixelegg template
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Stefan Reinhard <stefan.reinhard@pixelegg.de>
 * @package pixelegg
 * @version $Id$
 */

/**
 * @todo extend Stylite template preferences instead of this copy (simple include fails)
 */
$apps = $no_navbar_apps = array();
if (!$hook_data['setup'] && !isset($GLOBALS['egw_setup']))	// does not work on setup time
{
	foreach(ExecMethod('pixelegg.pixelegg_framework.navbar_apps') as $app => $data)
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
	unset($app); unset($data);
}

$colors = array(
	'#408dd2' => lang('LightBlue'),
	'#679fd2' => lang('DarkBlue'),
	'#B0C4DE' => lang('LightSteelBlue'),
	'#20B2AA' => lang('LightSeaGreen'),
	'#84CA8C' => lang('DarkGreen'),
	'#b4b4b4' => lang('Gray'),
);

asort($colors);
$colors['custom'] = lang('Custom color');	// custom allways last
$template_colors = array();
foreach($colors as $color => $label)
{
	$template_colors[$color] = $label.' ('.$color.') '.lang('Sidebox, header, and logo');
}
/**
 * Stylite Pixelegg template
 */
$GLOBALS['settings'] = array(
	'prefssection' => array(
		'type'   => 'section',
		'title'  => lang('Preferences for the %1 template set','Standard'),
		'no_lang'=> true,
		'xmlrpc' => False,
		'admin'  => False,
	),
	'show_generation_time' => array(
		'type'   => 'check',
		'label'  => 'Show page generation time',
		'name'   => 'show_generation_time',
		'help'   => 'Show page generation time on the bottom of the page?',
		'xmlrpc' => False,
		'admin'  => False,
		'forced' => false,
	),
	'open_tabs' => array(
		'type'    => 'select-tabs',
		'label'   => 'Open application tabs',
		'name'    => 'open_tabs',
		'values'  => $no_navbar_apps,
		'help'    => 'Allows to set a default or force the open application tabs.',
		'xmlrpc'  => True,
		'admin'   => False,
		'default' => 'addressbook,calendar,mail,filemanager,infolog,rocketchat',
	),
	'active_tab' => array(
		'type'    => 'select-tab',
		'label'   => 'Active application tab',
		'name'    => 'active_tab',
		'values'  => $no_navbar_apps,
		'help'    => 'Allows to set a default or force the active application tab for new logins.',
		'xmlrpc'  => True,
		'admin'   => False,
		'default' => 'calendar',
	),
	'template_color' => array(
		'type' => 'select',
		'label' => 'Template color',
		'no_lang' => true,
		'name' => 'template_color',
		'values' => $template_colors,
		'help' => 'Color used in template for active user interface elements. You need to reload (F5) after storing the preferences.',
		'xmlrpc' => True,
		'admin'  => False,
		'style' => 'width: 50%;'
	),
	'template_custom_color' => array(
		'type' => 'color',
		'label' => 'Custom color',
		'no_lang' => true,
		'name' => 'template_custom_color',
		'help' => lang('Use eg. %1 or %2','#FF0000','orange'),
		'xmlrpc' => True,
		'admin'  => False,
	),
	'sidebox_custom_color' => array(
		'type' => 'color',
		'label' => 'Custom sidebar menu active color, defaults to above color darkened',
		'no_lang' => true,
		'name' => 'sidebox_custom_color',
		'help' => lang('Use eg. %1 or %2','#FF0000','orange'),
		'xmlrpc' => True,
		'admin'  => False,
	),
	'loginbox_custom_color' => array(
		'type' => 'color',
		'label' => 'Custom login box color, defaults to above color darkened',
		'no_lang' => true,
		'name' => 'loginbox_custom_color',
		'help' => lang('Use eg. %1 or %2','#FF0000','orange'),
		'xmlrpc' => True,
		'admin'  => False,
	),
	'navbar_format' => false,	// not used in JDots (defined in common prefs)
	'default_app' => false,		// not used in JDots, as we can have multiple tabs open ...
);
unset($apps);