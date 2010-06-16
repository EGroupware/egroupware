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
		'label'  => 'Sidebar width stored for each application',
		'name'   => 'app_specific_sidebar_width',
		'help'   => 'When set, the width of the sidebar menu is not stored globaly but independently for each application',
		'xmlrpc' => false,
		'admin'  => false,
		'default'=> '0',
	),
	'navbar_format' => false,	// not used in JDots (defined in common prefs)
);
