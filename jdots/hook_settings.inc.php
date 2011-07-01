<?php
/**
 * Stylite: jdots template
 *
 * @link http://www.stylite.de
 * @package jdots
 * @author Andreas Stöckel <as@stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @author Nathan Gray <ng@stylite.de>
 * @version $Id$
 */

$apps = $no_navbar_apps = array();
if (!$hook_data['setup'])	// does not work on setup time
{
	foreach(ExecMethod('jdots.jdots_framework.navbar_apps') as $app => $data)
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
	'#4b89d8' => lang('dark blue'),
	'#010101' => lang('black'),
	'#a3620a' => lang('brown'),
	'#469609' => lang('dark green'),
	'#bbde62' => lang('light green'),
	'#9541d6' => lang('lila'),
	'#f7b21e' => lang('orange'),
	'#fe6a07' => lang('dark orange'),
	'#fedf9e' => lang('light orange'),
	'#f06eaa' => lang('pink'),
	'#ed1c24' => lang('red'),
	'#fef322' => lang('yellow'),
);
asort($colors);
$colors['custom'] = lang('Custom color');	// custom allways last
$template_colors = array();
foreach($colors as $color => $label)
{
	$template_colors[$color] = $label.' ('.$color.') '.lang('Sidebox and header');
	$template_colors['@'.$color] = $label.' ('.$color.') '.lang('Sidebox and active tab');
}

/**
 * Stylite jdots template
 */
$GLOBALS['settings'] = array(
	'prefssection' => array(
		'type'   => 'section',
		'title'  => lang('Preferences for the %1 template set','Stylite'),
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
	'app_specific_sidebar_width' => array(
		'type'   => 'check',
		'label'  => 'Store sidebar width for each application',
		'name'   => 'app_specific_sidebar_width',
		'help'   => 'When set, the width of the sidebar menu is not stored globaly but independently for each application',
		'xmlrpc' => false,
		'admin'  => false,
		'default'=> '0',
	),
	'open_tabs' => array(
		'type'   => 'multiselect',
		'label'  => 'Open application tabs',
		'name'   => 'open_tabs',
		'values' => $apps,
		'help'   => 'Allows to set a default or force the open application tabs.',
		'xmlrpc' => True,
		'admin'  => False,
		'default' => 'addressbook,calendar',
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
	/*'remote_application_enabled' => array(
		'type'	=> 'check',
		'name' => 'remote_application_enabled',
		'label' => 'Remote application enabled',
		'help' => 'If you wish to include an application from another egw instance inside the Stylite template set, set this option.',
		'no_lang' => true,
		'xmlrpc' => false,
		'admin' => true,
		'forced' => true,
		'default' => 0
	),
	'remote_application_name' => array(
		'type'	=> 'input',
		'name' => 'remote_application_name',
		'label' => 'Remote application name',
		'help' => 'The identifier of the application that should be included. Specifiy the internal lowercase name here, not the translated. E.g. "calendar" or "tracker"',
		'no_lang' => true,
		'xmlrpc' => false,
		'admin' => true,
		'forced' => true,
		'default' => ' '
	),
	'remote_application_title' => array(
		'type'	=> 'input',
		'name' => 'remote_application_title',
		'label' => 'Remote application title',
		'help' => 'How the remote application should be called on your instance. E.g. "Remote Tracker"',
		'no_lang' => true,
		'xmlrpc' => false,
		'admin' => true,
		'forced' => true,
		'default' => ' '
	),
	'remote_application_url' => array(
		'type'	=> 'input',
		'name' => 'remote_application_url',
		'label' => 'Remote instance url',
		'help' => 'The base url of the remote egroupware instance. E.g. "http://egw.myegw.org/egw/" ',
		'no_lang' => true,
		'xmlrpc' => false,
		'admin' => true,
		'forced' => true,
		'default' => ' '
	),*/

	'navbar_format' => false,	// not used in JDots (defined in common prefs)
	'default_app' => false,		// not used in JDots, as we can have multiple tabs open ...
);
unset($apps);
