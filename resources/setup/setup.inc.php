<?php
/**
 * eGroupWare - resources
 * http://www.egroupware.org
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @subpackage setup
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
 * @version $Id$
 */

$setup_info['resources']['name']	= 'resources';
$setup_info['resources']['title']	= 'Resources';
$setup_info['resources']['version']	= '1.9.001';
$setup_info['resources']['app_order']	= 5;
$setup_info['resources']['tables']	= array('egw_resources','egw_resources_extra');
$setup_info['resources']['enable']	= 1;

$setup_info['resources']['author']	= 'Cornelius Weiss';
$setup_info['resources']['license']	= 'GPL';
$setup_info['resources']['description'] = 'A resource management and booking system, which integrates into eGroupWare\'s calendar.';
$setup_info['resources']['note']	= '';
$setup_info['resources']['maintainer']	= array(
	'name' => 'eGroupware coreteam',
	'email' => 'egroupware-developers@lists.sf.net'
);

$setup_info['resources']['hooks']['preferences']	= 'resources.resources_hooks.admin_prefs_sidebox';
$setup_info['resources']['hooks']['admin']		= 'resources.resources_hooks.admin_prefs_sidebox';
$setup_info['resources']['hooks']['sidebox_menu']	= 'resources.resources_hooks.admin_prefs_sidebox';
$setup_info['resources']['hooks']['search_link']	= 'resources.resources_hooks.search_link';
$setup_info['resources']['hooks']['calendar_resources']	= 'resources.resources_hooks.calendar_resources';
$setup_info['resources']['hooks']['delete_category']	= 'resources.resources_hooks.delete_category';
$setup_info['resources']['hooks']['settings'] = 'resources_hooks::settings';
//	$setup_info['resources']['hooks'][]	= 'home';
//	$setup_info['resources']['hooks'][]	= 'settings';

$setup_info['resources']['depends'][]	= array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.7','1.8','1.9')
);
$setup_info['resources']['depends'][]	= array( // cause eTemplates is not in the api yet
	 'appname' => 'etemplate',
	 'versions' => Array('1.7','1.8','1.9')
);
